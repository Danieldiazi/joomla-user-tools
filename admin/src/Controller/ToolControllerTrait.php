<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

/** Token, permission and error handling shared by the POST controllers. */
trait ToolControllerTrait
{
    protected function guard(string $permission): void
    {
        $this->checkToken();
        if (!$this->app->getIdentity()->authorise($permission, 'com_usertools')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
    }

    protected function go(string $query): void
    {
        $this->setRedirect('index.php?option=com_usertools&' . $query);
    }

    protected function warn(UserFacingException $e): void
    {
        $this->app->enqueueMessage(Text::sprintf($e->key(), ...array_map(
            fn ($a) => \is_string($a) ? htmlspecialchars($a, ENT_QUOTES, 'UTF-8') : $a,
            $e->arguments()
        )), 'warning');
    }

    /** @return array<string, mixed> */
    protected function postData(): array
    {
        $post = $this->input->post;
        return [
            'names'           => $post->get('names', '', 'RAW'),
            'excluded'        => $post->get('excluded', '', 'RAW'),
            'excluded_groups' => $post->get('excluded_groups', [], 'ARRAY'),
            'protect_admins'  => $post->getInt('protect_admins', 1),
            'preview'         => $post->getString('preview', ''),
            'review_ready'    => $post->getInt('review_ready', 0),
            'selection_ready' => $post->getInt('selection_ready', 0),
            'registrations'   => $post->get('registrations', '{}', 'RAW'),
            'selected'        => $post->get('selected', '[]', 'RAW'),
            'creation_mode'   => $post->getCmd('creation_mode', ''),
            'confirm'         => $post->getInt('confirm', 0),
            'confirm_count'   => trim($post->getString('confirm_count', '')),
            'days'            => $post->getInt('days', 365),
            'include_never'   => $post->getInt('include_never', 0),
        ];
    }
}
