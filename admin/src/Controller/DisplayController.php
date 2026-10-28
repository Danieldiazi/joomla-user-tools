<?php

namespace Danieldiazi\Component\Usertools\Administrator\Controller;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Toolkit;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/** Shows the screens. Each view requires its own permission (see access.xml). */
class DisplayController extends BaseController
{
    protected $default_view = 'home';

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
    ];

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
        if (!$this->app->getIdentity()->authorise(self::PERMISSIONS[$view], 'com_usertools')) {
            throw new \Joomla\CMS\Access\Exception\NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        // Reviews and one-time passwords must never be cached by the browser or a proxy.
        Toolkit::app()->setHeader('Cache-Control', 'no-store, private', true);
        return parent::display(false, $urlparams);
    }
}
