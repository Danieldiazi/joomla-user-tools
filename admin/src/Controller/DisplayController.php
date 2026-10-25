<?php
namespace Danieldiazi\Component\Usertools\Administrator\Controller;

defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

class DisplayController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        $app = Factory::getApplication();
        $actor = $app->getIdentity();
        if (!$actor->authorise('core.admin')) { throw new \RuntimeException('Solo superusuarios.', 403); }
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $session = $app->getSession();
        $input = $app->getInput();
        $settings = Exclusions::fromArray(ComponentHelper::getParams('com_usertools')->toArray());
        $draft = $session->get('usertools.draft', ['text' => '', 'settings' => $settings->toArray(), 'checked' => [], 'emails' => []]);
        $preview = $session->get('usertools.preview', []);
        $validPreview = $preview && ($preview['expires'] ?? 0) >= time()
            && ($preview['actor'] ?? 0) === (int) $actor->id
            && ($preview['settings'] ?? []) === $settings->toArray()
            && isset($preview['unknown']);
        if (!$validPreview) { $preview = []; $session->set('usertools.preview', []); }
        $step = $input->getCmd('step', 'home');
        if (!in_array($step, ['home', 'edit', 'review', 'result', 'schedule'], true)) { $step = 'home'; }

        if ($input->getMethod() === 'POST') {
            if (!Session::checkToken()) { throw new \RuntimeException('La sesión ha caducado.', 403); }
            $operation = $input->post->getCmd('operation');
            try {
                if (in_array($operation, ['compare', 'save_exclusions'], true)) {
                    $settings = Exclusions::fromArray([
                        'protect_admins' => $input->post->getInt('protect_admins', 1),
                        'excluded_names' => $input->post->get('excluded', '', 'RAW'),
                        'excluded_groups' => $input->post->get('excluded_groups', [], 'ARRAY'),
                    ]);
                    $draft['text'] = $input->post->get('names', '', 'RAW');
                    $draft['settings'] = $settings->toArray();
                    $session->set('usertools.draft', $draft);
                    $session->set('usertools.preview', []);
                    $this->saveSettings($settings);
                    if ($operation === 'save_exclusions') {
                        $app->enqueueMessage('Exclusiones de la comparación manual guardadas.');
                        return $this->go('edit');
                    }
                    $names = Comparison::parse($draft['text']);
                    if (!$names) { throw new \InvalidArgumentException('Pega la lista completa de usuarios vigentes.'); }
                    $comparison = $this->calculate($names, $settings);
                    $preview = [
                        'names' => $names, 'settings' => $settings->toArray(),
                        'ids' => array_map(fn($u) => (int) $u->id, $comparison['candidates']),
                        'unknown' => array_map('strval', $comparison['unknown']),
                        'expires' => time() + 900, 'actor' => (int) $actor->id,
                        'nonce' => bin2hex(random_bytes(24)),
                    ];
                    $session->set('usertools.preview', $preview);
                    return $this->go('review');
                }
                if (in_array($operation, ['back', 'apply'], true)) {
                    if (!$preview || !hash_equals($preview['nonce'], $input->post->getString('preview'))) {
                        throw new \InvalidArgumentException('La revisión ha caducado o han cambiado las exclusiones. Vuelve a comparar.');
                    }
                    if ($input->post->getInt('review_ready') !== 1) {
                        throw new \InvalidArgumentException('Activa JavaScript para guardar la selección de altas.');
                    }
                    $selectionDraft = Registration::draft($input->post->get('registrations', '{}', 'RAW'), $preview['unknown']);
                    $draft = array_replace($draft, $selectionDraft);
                    $session->set('usertools.draft', $draft);
                    if ($operation === 'back') {
                        $session->set('usertools.preview', []);
                        return $this->go('edit');
                    }
                    if ($input->post->getInt('confirm') !== 1) { throw new \InvalidArgumentException('Confirma los cambios antes de aplicarlos.'); }
                    $selection = Registration::select(
                        $preview['unknown'], $draft['checked'], $draft['emails'],
                        fn($email) => $this->exists('email', $email),
                        fn($name) => $this->exists('username', $name),
                    );
                    if ($selection) { $this->assertRegisteredGroup(); }
                    // Consume the token before any writes. Refresh/replay cannot repeat the batch.
                    $session->set('usertools.preview', []);
                    $result = ['blocked' => [], 'created' => [], 'errors' => [], 'actor' => (int) $actor->id, 'expires' => time() + 900];
                    $fresh = $this->calculate($preview['names'], $settings);
                    $factory = Factory::getContainer()->get(UserFactoryInterface::class);
                    foreach ($fresh['candidates'] as $candidate) {
                        if (!in_array((int) $candidate->id, $preview['ids'], true)) { continue; }
                        $user = $factory->loadUserById((int) $candidate->id);
                        if (!$user->id || $user->block || $settings->protects($user, (int) $actor->id)
                            || in_array($user->username, $preview['names'], true)) { continue; }
                        $user->block = 1;
                        try {
                            if (!$user->save()) { throw new \RuntimeException('Guardado rechazado.'); }
                            $result['blocked'][] = $user->username;
                        } catch (\Throwable $e) {
                            $result['errors'][] = ['username' => $user->username, 'message' => 'No se pudo bloquear la cuenta.'];
                        }
                    }
                    if ($selection) {
                        // Prevent the core user plugin from emailing generated passwords.
                        PluginHelper::importPlugin('user');
                        $corePlugin = $app->bootPlugin('joomla', 'user');
                        $mailToUser = $corePlugin->params->get('mail_to_user', 1);
                        $corePlugin->params->set('mail_to_user', 0);
                        try {
                            $new = Registration::create($selection, fn() => $factory->loadUserById(0), 2,
                                (int) ComponentHelper::getParams('com_users')->get('minimum_length', 24));
                        } finally {
                            $corePlugin->params->set('mail_to_user', $mailToUser);
                        }
                        $result['created'] = $new['created'];
                        $result['errors'] = array_merge($result['errors'], $new['errors']);
                    }
                    $session->set('usertools.result', $result);
                    $session->set('usertools.draft', ['text' => '', 'settings' => $settings->toArray(), 'checked' => [], 'emails' => []]);
                    return $this->go('result');
                }
            } catch (\InvalidArgumentException $e) {
                $app->enqueueMessage($e->getMessage(), 'warning');
                return $this->go(in_array($operation, ['back', 'apply'], true) && $preview ? 'review' : 'edit');
            }
            return $this->go('home');
        }

        $comparison = [];
        if ($step === 'review') {
            if (!$preview) {
                $app->enqueueMessage('Vuelve a comparar para revisar los cambios.', 'warning');
                return $this->go('edit');
            }
            $comparison = $this->calculate($preview['names'], $settings);
            $comparison['candidates'] = array_filter($comparison['candidates'], fn($u) => in_array((int) $u->id, $preview['ids'], true));
            $comparison['unknown'] = array_values(array_intersect($preview['unknown'], array_map('strval', $comparison['unknown'])));
        }
        $result = $session->get('usertools.result', []);
        if ($result && (($result['actor'] ?? 0) !== (int) $actor->id || ($result['expires'] ?? 0) < time())) {
            $result = []; $session->set('usertools.result', []);
        }
        if ($step === 'result' && !$result) { return $this->go('home'); }
        if ($step === 'result') {
            // Passwords are rendered once, never stored in the persistent result.
            $redacted = $result;
            foreach ($redacted['created'] as &$row) { unset($row['password']); }
            unset($row);
            $session->set('usertools.result', $redacted);
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $groups = $db->setQuery($db->createQuery()->select($db->quoteName(['id', 'title']))
            ->from($db->quoteName('#__usergroups'))->order($db->quoteName('lft')))->loadObjectList();
        $pluginInstalled = (bool) $db->setQuery($db->createQuery()->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('usertools')))->loadResult();
        $pluginEnabled = PluginHelper::isEnabled('task', 'usertools');
        $app->getDocument()->addStyleSheet(Uri::root(true) . '/media/com_usertools/css/admin.css');
        $app->getDocument()->addScript(Uri::root(true) . '/media/com_usertools/js/admin.js', [], ['defer' => true]);
        require JPATH_ADMINISTRATOR . '/components/com_usertools/tmpl/wizard.php';
        return $this;
    }

    private function go(string $step): self
    {
        Factory::getApplication()->redirect('index.php?option=com_usertools&step=' . $step);
        return $this;
    }

    private function exists(string $field, string $value): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        return (bool) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__users'))
            ->where($db->quoteName($field) . ' = ' . $db->quote($value)))->loadResult();
    }

    private function assertRegisteredGroup(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $exists = $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('id') . ' = 2'))->loadResult();
        if (!$exists || Access::checkGroup(2, 'core.admin') || Access::checkGroup(2, 'core.login.admin')) {
            throw new \InvalidArgumentException('El grupo Registrados (ID 2) no existe o tiene permisos de administración. Revisa los grupos antes de dar altas.');
        }
    }

    private function saveSettings(Exclusions $settings): void
    {
        $params = ComponentHelper::getParams('com_usertools');
        foreach ($settings->toArray() as $key => $value) { $params->set($key, $value); }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery($db->createQuery()->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote((string) $params))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_usertools'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component')))->execute();
    }

    private function calculate(array $names, Exclusions $settings): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $users = $db->setQuery($db->createQuery()->select($db->quoteName(['id', 'username', 'block']))
            ->from($db->quoteName('#__users')))->loadObjectList();
        $actorId = (int) Factory::getApplication()->getIdentity()->id;
        $factory = Factory::getContainer()->get(UserFactoryInterface::class);
        $protected = [];
        foreach ($users as $record) {
            if ($settings->protects($factory->loadUserById((int) $record->id), $actorId)) { $protected[] = (int) $record->id; }
        }
        return Comparison::compare($users, $names, $settings->names, $protected);
    }
}
