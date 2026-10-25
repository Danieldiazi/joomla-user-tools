<?php

/**
 * Package installer: checks versions, migrates options from 2026.10.2 and earlier, registers the
 * extension in the User Actions Log and creates the "account created" mail template.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\Component\Mails\Administrator\Model\TemplateModel;
use Joomla\CMS\Table\Extension;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class Pkg_UsertoolsInstallerScript extends InstallerScript
{
    protected $minimumPhp    = '8.3.0';
    protected $minimumJoomla = '6.0.0';

    private const MAIL_TEMPLATE = 'com_usertools.account_created';
    private const MAIL_TAGS     = ['name', 'username', 'sitename', 'siteurl', 'link_text', 'link_html', 'token'];

    public function preflight($type, $parent)
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_USERTOOLS_ERROR_PHP', $this->minimumPhp, PHP_VERSION), 'error');
            return false;
        }
        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_USERTOOLS_ERROR_JOOMLA', $this->minimumJoomla, JVERSION), 'error');
            return false;
        }
        return true;
    }

    /**
     * @param  string           $type
     * @param  InstallerAdapter $parent
     * @return bool
     */
    public function postflight($type, $parent)
    {
        if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->migrateOptions($db);
        $this->registerActionLog($db);
        $this->createMailTemplate();
        if ($type !== 'update') {
            $this->enablePlugin($db);
        }
        Factory::getApplication()->enqueueMessage(Text::_($type === 'update' ? 'PKG_USERTOOLS_UPDATED' : 'PKG_USERTOOLS_INSTALLED'));
        return true;
    }

    /**
     * @param  InstallerAdapter $parent
     * @return bool
     */
    public function uninstall($parent)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        try {
            $db->setQuery($db->createQuery()->delete($db->quoteName('#__action_logs_extensions'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_usertools')))->execute();
            $db->setQuery($db->createQuery()->delete($db->quoteName('#__mail_templates'))
                ->where($db->quoteName('template_id') . ' = ' . $db->quote(self::MAIL_TEMPLATE)))->execute();
        } catch (\Throwable) {
        }
        $logs = rtrim((string) Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\') . '/usertools-reports';
        Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_USERTOOLS_UNINSTALLED', htmlspecialchars($logs, ENT_QUOTES, 'UTF-8')));
        return true;
    }

    /** Options of 2026.10.2 and earlier: compare settings at top level, inactivity settings nested. */
    private function migrateOptions(DatabaseInterface $db): void
    {
        $id = (int) $db->setQuery($db->createQuery()->select($db->quoteName('extension_id'))->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_usertools')))->loadResult();
        if (!$id) {
            return;
        }
        $table = new Extension($db);
        if (!$table->load($id)) {
            return;
        }
        $params = new Registry($table->params);
        if ($params->exists('compare_protect_admins') || (!$params->exists('protect_admins') && !$params->exists('manual_inactivity'))) {
            return;
        }
        $compare  = ['protect_admins' => $params->get('protect_admins', 1), 'excluded_names' => $params->get('excluded_names', ''), 'excluded_groups' => (array) $params->get('excluded_groups', [])];
        $inactive = (array) $params->get('manual_inactivity', []);
        $values   = [
            'compare_protect_admins'   => (int) $compare['protect_admins'],
            'compare_excluded_names'   => (string) $compare['excluded_names'],
            'compare_excluded_groups'  => array_map('intval', $compare['excluded_groups']),
            'inactive_days'            => (int) ($inactive['days'] ?? 365),
            'inactive_include_never'   => (int) ($inactive['include_never'] ?? 0),
            'inactive_protect_admins'  => (int) ($inactive['protect_admins'] ?? 1),
            'inactive_excluded_names'  => (string) ($inactive['excluded_names'] ?? ''),
            'inactive_excluded_groups' => array_map('intval', (array) ($inactive['excluded_groups'] ?? [])),
        ];
        foreach (['protect_admins', 'excluded_names', 'excluded_groups', 'manual_inactivity'] as $old) {
            $params->remove($old);
        }
        foreach ($values as $key => $value) {
            $params->set($key, $value);
        }
        $table->params = $params->toString();
        $table->store();
    }

    private function registerActionLog(DatabaseInterface $db): void
    {
        try {
            $exists = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__action_logs_extensions'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_usertools')))->loadResult();
            if (!$exists) {
                $row = (object) ['extension' => 'com_usertools'];
                $db->insertObject('#__action_logs_extensions', $row);
            }
        } catch (\Throwable) {
        }
    }

    /** Base template (language-independent). Customised translations in Mail Templates are kept. */
    private function createMailTemplate(): void
    {
        $key = 'COM_USERTOOLS_MAIL_ACCOUNT_CREATED';
        try {
            $this->mailModel()->save([
                'template_id' => self::MAIL_TEMPLATE,
                'extension'   => 'com_usertools',
                'language'    => '',
                'subject'     => $key . '_SUBJECT',
                'body'        => $key . '_BODY',
                'htmlbody'    => $key . '_HTMLBODY',
                'attachments' => '',
                'params'      => json_encode(['tags' => self::MAIL_TAGS]),
            ]);
        } catch (\Throwable) {
        }
    }

    private function mailModel(): TemplateModel
    {
        $component = Factory::getApplication()->bootComponent('com_mails');
        $model     = $component instanceof MVCFactoryServiceInterface
            ? $component->getMVCFactory()->createModel('Template', 'Administrator', ['ignore_request' => true]) : null;
        if (!$model instanceof TemplateModel) {
            throw new \RuntimeException('com_mails is not available.');
        }
        return $model;
    }

    private function enablePlugin(DatabaseInterface $db): void
    {
        $db->setQuery($db->createQuery()->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('usertools')))->execute();
    }
}
