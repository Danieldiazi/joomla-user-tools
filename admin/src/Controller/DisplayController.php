<?php
namespace Danieldiazi\Component\Usertools\Administrator\Controller;

defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

class DisplayController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        $app = Factory::getApplication();
        $actor = $app->getIdentity();
        if (!$actor->authorise('core.admin')) {
            throw new \RuntimeException('Solo superusuarios.', 403);
        }
        $session = $app->getSession();
        $input = $app->getInput();
        $settings = Exclusions::fromArray(ComponentHelper::getParams('com_usertools')->toArray());
        $state = $session->get('usertools.preview', []);
        if ($state && (($state['expires'] ?? 0) < time()
            || ($state['settings'] ?? []) !== $settings->toArray())) {
            $state = [];
            $session->set('usertools.preview', []);
        }

        if ($input->getMethod() === 'POST') {
            if (!Session::checkToken()) {
                throw new \RuntimeException('La sesión ha caducado.', 403);
            }
            $action = $input->post->getCmd('operation');
            // Previews are single-use, including invalid requests.
            $session->set('usertools.preview', []);
            if (in_array($action, ['compare', 'save_exclusions'], true)) {
                try {
                    $settings = Exclusions::fromArray([
                        'protect_admins' => $input->post->getInt('protect_admins', 1),
                        'excluded_names' => $input->post->get('excluded', '', 'RAW'),
                        'excluded_groups' => $input->post->get('excluded_groups', [], 'ARRAY'),
                    ]);
                    if ($action === 'save_exclusions') {
                        $this->saveSettings($settings);
                        $app->enqueueMessage('Exclusiones de la comparación manual guardadas.');
                    } else {
                        $text = $input->post->get('names', '', 'RAW');
                        $names = Comparison::parse($text);
                        if (!$names) {
                            throw new \InvalidArgumentException('Pega una lista completa de usuarios vigentes.');
                        }
                        $result = $this->calculate($names, $settings);
                        $this->saveSettings($settings);
                        $session->set('usertools.preview', [
                            'text' => $text, 'names' => $names, 'settings' => $settings->toArray(),
                            'ids' => array_map(fn($u) => (int) $u->id, $result['candidates']),
                            'expires' => time() + 900, 'actor' => (int) $actor->id,
                            'nonce' => bin2hex(random_bytes(24)),
                        ]);
                    }
                } catch (\InvalidArgumentException $e) {
                    $app->enqueueMessage($e->getMessage(), 'warning');
                }
            } elseif ($action === 'block') {
                if (!$state || $state['actor'] !== (int) $actor->id
                    || !hash_equals($state['nonce'], $input->post->getString('preview'))
                    || $input->post->getInt('confirm') !== 1) {
                    $app->enqueueMessage('La vista previa ha cambiado o caducado. Compara de nuevo.', 'warning');
                } else {
                    $fresh = $this->calculate($state['names'], $settings);
                    $count = 0;
                    $failed = 0;
                    $factory = Factory::getContainer()->get(UserFactoryInterface::class);
                    foreach ($fresh['candidates'] as $candidate) {
                        if (!in_array((int) $candidate->id, $state['ids'], true)) {
                            continue;
                        }
                        $user = $factory->loadUserById((int) $candidate->id);
                        if (!$user->id || $user->block || $settings->protects($user, (int) $actor->id)
                            || in_array($user->username, $state['names'], true)) {
                            continue;
                        }
                        $user->block = 1;
                        try {
                            if ($user->save()) { $count++; } else { $failed++; }
                        } catch (\Throwable $e) {
                            $failed++;
                        }
                    }
                    $app->enqueueMessage("Cuentas bloqueadas: $count. Errores: $failed.");
                }
            }
            $app->redirect('index.php?option=com_usertools');
            return $this;
        }

        $escape = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<h1>User Tools</h1><h2>Comparación manual</h2>';
        echo '<p>Pega la lista completa de usuarios vigentes, un nombre por línea. Se distinguen mayúsculas y minúsculas. No se crean, eliminan ni reactivan cuentas.</p>';
        echo '<p>Las exclusiones de esta pantalla son independientes de las de cada tarea programada. Tu propia cuenta siempre queda protegida.</p>';
        echo '<form method="post" action="index.php?option=com_usertools">';
        echo '<label for="names"><strong>Usuarios vigentes</strong></label><textarea id="names" name="names" class="form-control" rows="14" required>' . $escape($state['text'] ?? '') . '</textarea>';
        echo '<fieldset class="mt-3"><legend>Exclusiones de la comparación manual</legend>';
        echo '<input type="hidden" name="protect_admins" value="0"><label><input type="checkbox" name="protect_admins" value="1"' . ($settings->protectAdmins ? ' checked' : '') . '> Excluir administradores y usuarios con acceso a la administración</label>';
        echo '<label for="excluded" class="d-block mt-3">Nombres de usuario excluidos (uno por línea)</label><textarea id="excluded" name="excluded" class="form-control" rows="4">' . $escape(implode("\n", $settings->names)) . '</textarea>';
        echo '<label for="excluded_groups" class="d-block mt-3">Grupos excluidos</label><select id="excluded_groups" name="excluded_groups[]" multiple size="8" class="form-select">';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select($db->quoteName(['id', 'title']))->from($db->quoteName('#__usergroups'))->order($db->quoteName('lft'));
        foreach ($db->setQuery($query)->loadObjectList() as $group) {
            echo '<option value="' . (int) $group->id . '"' . (in_array((int) $group->id, $settings->groups, true) ? ' selected' : '') . '>' . $escape($group->title) . '</option>';
        }
        echo '</select><p>Selecciona varios grupos con Ctrl (o Cmd). Se incluyen sus subgrupos. Se excluye una cuenta si cumple cualquiera de estas condiciones.</p>';
        echo '<button class="btn btn-secondary" name="operation" value="save_exclusions" formnovalidate>Guardar exclusiones</button></fieldset>';
        echo '<p class="mt-3"><button class="btn btn-primary" name="operation" value="compare">Comparar</button></p>' . HTMLHelper::_('form.token') . '</form>';
        echo '<h2>Tarea programada por inactividad</h2><p>Habilita el plugin «Tarea - User Tools» y crea una tarea «User Tools: bloquear usuarios inactivos» en Sistema → Tareas programadas. Configura allí su plazo y sus propias exclusiones.</p>';

        if ($state) {
            $result = $this->calculate($state['names'], $settings);
            // Never display new candidates that were not included in the stored preview.
            $result['candidates'] = array_filter($result['candidates'], fn($u) => in_array((int) $u->id, $state['ids'], true));
            echo '<h2 class="mt-4">Vista previa</h2><p>Coincidencias: ' . $result['matched'] . '. Protegidos: ' . $result['protected'] . '. Ya bloqueados: ' . $result['blocked'] . '.</p>';
            if ($result['unknown']) {
                echo '<details><summary>Nombres sin cuenta en Joomla: ' . count($result['unknown']) . '</summary><pre>' . $escape(implode("\n", $result['unknown'])) . '</pre></details>';
            }
            echo '<h3>Cuentas que se bloquearían: ' . count($result['candidates']) . '</h3><ul>';
            foreach ($result['candidates'] as $user) {
                echo '<li>' . $escape($user->username) . '</li>';
            }
            echo '</ul>';
            if ($result['matched'] === 0) {
                echo '<div class="alert alert-warning">No hay coincidencias. Se impide aplicar bloqueos.</div>';
            } elseif ($result['candidates']) {
                echo '<form method="post" action="index.php?option=com_usertools"><input type="hidden" name="preview" value="' . $escape($state['nonce']) . '"><label><input type="checkbox" name="confirm" value="1" required> He revisado la lista completa y confirmo estos bloqueos.</label><p><button class="btn btn-danger" name="operation" value="block">Bloquear los usuarios indicados</button></p>' . HTMLHelper::_('form.token') . '</form>';
            }
        }
        return $this;
    }

    private function saveSettings(Exclusions $settings): void
    {
        $params = ComponentHelper::getParams('com_usertools');
        foreach ($settings->toArray() as $key => $value) {
            $params->set($key, $value);
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_usertools'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($query)->execute();
    }

    private function calculate(array $names, Exclusions $settings): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select($db->quoteName(['id', 'username', 'block']))->from($db->quoteName('#__users'));
        $users = $db->setQuery($query)->loadObjectList();
        $actorId = (int) Factory::getApplication()->getIdentity()->id;
        $protected = [];
        $factory = Factory::getContainer()->get(UserFactoryInterface::class);
        foreach ($users as $record) {
            if ($settings->protects($factory->loadUserById((int) $record->id), $actorId)) {
                $protected[] = (int) $record->id;
            }
        }
        return Comparison::compare($users, $names, $settings->names, $protected);
    }
}
