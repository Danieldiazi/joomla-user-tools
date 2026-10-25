<?php
namespace Danieldiazi\Component\Usertools\Administrator\Controller;
defined('_JEXEC') or die;
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

class DisplayController extends BaseController {
    public function display($cachable = false, $urlparams = []) {
        $app = Factory::getApplication();
        $actor = $app->getIdentity();
        if (!$actor->authorise('core.admin')) { throw new \RuntimeException('Solo superusuarios.', 403); }
        $session = $app->getSession();
        $input = $app->getInput();
        $state = $session->get('usertools.preview', []);
        $excludedText = ComponentHelper::getParams('com_usertools')->get('excluded_names', '');
        if ($state && $state['expires'] < time()) { $state = []; $session->set('usertools.preview', []); }
        if ($input->getMethod() === 'POST') {
            if (!Session::checkToken()) { throw new \RuntimeException('La sesión ha caducado.', 403); }
            $action = $input->post->getCmd('operation');
            if ($action === 'compare') {
                $session->set('usertools.preview', []);
                try {
                    $text = $input->post->get('names', '', 'RAW');
                    $excludedText = $input->post->get('excluded', '', 'RAW');
                    $names = Comparison::parse($text);
                    $excluded = Comparison::parse($excludedText);
                    if (!$names) { throw new \InvalidArgumentException('Pega una lista completa de usuarios vigentes.'); }
                    $result = $this->calculate($names, $excluded);
                    $state = ['text' => $text, 'excluded' => $excludedText, 'names' => $names,
                        'ids' => array_map(fn($u) => (int) $u->id, $result['candidates']),
                        'expires' => time() + 900, 'actor' => (int) $actor->id,
                        'nonce' => bin2hex(random_bytes(24))];
                    $session->set('usertools.preview', $state);
                    $params = ComponentHelper::getParams('com_usertools');
                    $params->set('excluded_names', $excludedText);
                    $db = Factory::getContainer()->get(DatabaseInterface::class);
                    $query = $db->getQuery(true)->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('com_usertools'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
                    $db->setQuery($query)->execute();
                } catch (\InvalidArgumentException $e) { $app->enqueueMessage($e->getMessage(), 'warning'); }
            } elseif ($action === 'block') {
                $session->set('usertools.preview', []);
                if (!$state || $state['actor'] !== (int) $actor->id
                    || !hash_equals($state['nonce'], $input->post->getString('preview'))
                    || $input->post->getInt('confirm') !== 1) {
                    $app->enqueueMessage('Compara de nuevo y confirma los bloqueos.', 'warning');
                } else {
                    $fresh = $this->calculate($state['names'], Comparison::parse($state['excluded']));
                    $count = 0; $failed = 0;
                    foreach ($fresh['candidates'] as $candidate) {
                        if (!in_array((int) $candidate->id, $state['ids'], true)) { continue; }
                        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $candidate->id);
                        if (!$user->id || $user->block || $user->authorise('core.admin')
                            || $user->authorise('core.login.admin') || $user->id == $actor->id
                            || in_array($user->username, $state['names'], true)
                            || in_array($user->username, Comparison::parse($state['excluded']), true)) { continue; }
                        $user->block = 1;
                        try { if ($user->save()) { $count++; } else { $failed++; } }
                        catch (\Throwable $e) { $failed++; }
                    }
                    $app->enqueueMessage("Cuentas bloqueadas: $count. Errores: $failed.");
                }
            }
            $app->redirect('index.php?option=com_usertools');
            return $this;
        }
        $escape = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<h1>User Tools</h1><p>Pega la lista completa de usuarios vigentes, un nombre de usuario por línea. Se distinguen mayúsculas y minúsculas.</p>';
        echo '<p>Las cuentas ausentes se bloquearán tras tu confirmación. Se protegen tu cuenta y las cuentas con acceso a la administración. No se crean, eliminan ni reactivan usuarios.</p>';
        echo '<form method="post" action="index.php?option=com_usertools">';
        echo '<label for="names"><strong>Usuarios vigentes</strong></label><textarea id="names" name="names" class="form-control" rows="14" required>' . $escape($state['text'] ?? '') . '</textarea>';
        echo '<label for="excluded" class="mt-3"><strong>Cuentas del sistema excluidas (un nombre por línea)</strong></label><textarea id="excluded" name="excluded" class="form-control" rows="4">' . $escape($excludedText) . '</textarea>';
        echo '<p>Las exclusiones se guardan al comparar. Incluye las cuentas de servicio que deben conservar acceso.</p><button class="btn btn-primary" name="operation" value="compare">Comparar</button>' . HTMLHelper::_('form.token') . '</form>';
        if ($state) {
            $result = $this->calculate($state['names'], Comparison::parse($state['excluded']));
            echo '<h2 class="mt-4">Vista previa</h2><p>Coincidencias: ' . $result['matched'] . '. Protegidos: ' . $result['protected'] . '. Ya bloqueados: ' . $result['blocked'] . '.</p>';
            if ($result['unknown']) { echo '<details><summary>Nombres sin cuenta en Joomla: ' . count($result['unknown']) . '</summary><pre>' . $escape(implode("\n", $result['unknown'])) . '</pre></details>'; }
            echo '<h3>Cuentas que se bloquearían: ' . count($result['candidates']) . '</h3><ul>';
            foreach ($result['candidates'] as $user) { echo '<li>' . $escape($user->username) . '</li>'; }
            echo '</ul>';
            if ($result['matched'] === 0) {
                echo '<div class="alert alert-warning">No hay coincidencias. Se impide aplicar bloqueos: revisa la lista.</div>';
            } elseif ($result['candidates']) {
                echo '<form method="post" action="index.php?option=com_usertools"><input type="hidden" name="preview" value="' . $escape($state['nonce']) . '"><label><input type="checkbox" name="confirm" value="1" required> He revisado la lista completa y confirmo estos bloqueos.</label><p><button class="btn btn-danger" name="operation" value="block">Bloquear los usuarios indicados</button></p>' . HTMLHelper::_('form.token') . '</form>';
            }
        }
        return $this;
    }
    private function calculate(array $names, array $excluded): array {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName(['id', 'username', 'block']))->from($db->quoteName('#__users'));
        $users = $db->setQuery($query)->loadObjectList();
        $protected = [(int) Factory::getApplication()->getIdentity()->id];
        $factory = Factory::getContainer()->get(UserFactoryInterface::class);
        foreach ($users as $record) {
            $user = $factory->loadUserById((int) $record->id);
            if ($user->authorise('core.admin') || $user->authorise('core.login.admin')) { $protected[] = (int) $record->id; }
        }
        return Comparison::compare($users, $names, $excluded, $protected);
    }
}
