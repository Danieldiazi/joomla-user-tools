<?php

namespace Danieldiazi\Component\Usertools\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

/**
 * "Choose your password" email for new accounts. It uses the same token and confirmation page as
 * Joomla's password reset (com_users), with its own editable mail template (System > Mail Templates).
 */
final class AccountMailer
{
    public const TEMPLATE = 'com_usertools.account_created';

    public static function canSend(): bool
    {
        return (bool) Factory::getApplication()->get('mailonline', 1);
    }

    public function send(User $user): bool
    {
        if (!self::canSend()) {
            return false;
        }
        $app = Factory::getApplication();
        // Reload: the object used to create the account lacks database defaults (resetCount...).
        $user = Toolkit::users()->loadUserById((int) $user->id);
        if (!$user->id) {
            return false;
        }
        $token = ApplicationHelper::getHash(UserHelper::genRandomPassword());
        $user->activation = UserHelper::hashPassword($token);
        if (!$user->save(true)) {
            return false;
        }
        // Public site URL (honours $live_site). Routing the site from the administrator would point to /administrator.
        $root = Uri::root();
        if ((int) $app->get('force_ssl', 0) === 2) {
            $root = (string) preg_replace('#^http://#i', 'https://', $root);
        }
        $link = $root . 'index.php?option=com_users&view=reset&layout=confirm&token=' . $token;
        $site = ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        $mail = new MailTemplate(self::TEMPLATE, $site);
        $mail->addTemplateData([
            'name'      => $user->name,
            'username'  => $user->username,
            'sitename'  => $app->get('sitename'),
            'siteurl'   => $root,
            'link_text' => $link,
            'link_html' => htmlspecialchars($link, ENT_QUOTES, 'UTF-8'),
            'token'     => $token,
        ]);
        $mail->addRecipient($user->email, $user->name);
        return (bool) $mail->send();
    }
}
