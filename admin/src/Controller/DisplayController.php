<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/** Shows the screens. Each view requires its own permission (see access.xml). */
class DisplayController extends BaseController
{
    protected $default_view = 'home';

    /** Views for global Super Users only: checked on the root asset, never on the component's own rules. */
    public const SUPER_USERS = 'core.admin';

    /** Permission required by each view; the component itself already requires core.manage. */
    public const PERMISSIONS = [
        'home'     => 'core.manage',
        'compare'  => 'usertools.compare',
        'inactive' => 'usertools.inactive',
        'delete'   => 'usertools.delete',
        'schedule' => 'core.manage',
        'reports'  => 'usertools.reports',
        'report'   => 'usertools.reports',
        'access'   => 'usertools.access',
        'usage'    => 'usertools.access',
        'ungroup'  => 'usertools.ungroup',
        'diagnose' => 'usertools.diagnose',
        'age'      => 'usertools.content',
        'move'     => self::SUPER_USERS,
        'anonymize' => 'usertools.anonymize',
    ];

    /** Whether the user has a permission of PERMISSIONS (SUPER_USERS means a global Super User). */
    public static function allowed(\Joomla\CMS\User\User $user, string $permission): bool
    {
        return $permission === self::SUPER_USERS ? (bool) $user->authorise('core.admin') : (bool) $user->authorise($permission, 'com_usertools');
    }

    /**
     * @param  bool                 $cachable
     * @param  array<string, mixed> $urlparams
     * @return static
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->getCmd('view', $this->default_view);
        if (!isset(self::PERMISSIONS[$view])) {
            $view = $this->default_view;
            $this->input->set('view', $view);
        }
        if (!self::allowed($this->app->getIdentity(), self::PERMISSIONS[$view])) {
            throw new \Joomla\CMS\Access\Exception\NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        // Reviews and one-time passwords must never be cached by the browser or a proxy.
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        return parent::display(false, $urlparams);
    }
}
