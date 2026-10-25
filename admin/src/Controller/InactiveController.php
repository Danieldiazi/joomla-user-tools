<?php
namespace Danieldiazi\Component\Usertools\Administrator\Controller;
defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\InactivityPolicy;
use Danieldiazi\Component\Usertools\Administrator\Service\InactiveSelection;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

final class InactiveController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        $app = Factory::getApplication();
        $actor = $app->getIdentity();
        if (!$actor->authorise('core.admin')) { throw new \RuntimeException('Solo superusuarios.',403); }
        $app->setHeader('Cache-Control','no-store, private',true);
        $session = $app->getSession();
        $input = $app->getInput();
        $saved = (array) ComponentHelper::getParams('com_usertools')->get('manual_inactivity', []);
        $draft = $session->get('usertools.inactive.draft', $saved);
        $settings = Exclusions::fromArray($draft);
        $days = (int) ($draft['days'] ?? 365);
        $includeNever = (int) ($draft['include_never'] ?? 0) === 1;
        $stage = $input->getCmd('stage','setup');
        if (!in_array($stage,['setup','review','result'],true)) { $stage='setup'; }
        $preview = $session->get('usertools.inactive.preview',[]);
        if ($preview && ($preview['expires'] < time() || $preview['actor'] !== (int)$actor->id
            || $preview['settings'] !== $saved)) {
            $preview=[]; $session->set('usertools.inactive.preview',[]);
        }
        if ($input->getMethod()==='POST') {
            if (!Session::checkToken()) { throw new \RuntimeException('La sesión ha caducado.',403); }
            $operation=$input->post->getCmd('operation');
            try {
                if ($operation==='search') {
                    $days=$input->post->getInt('days',365);
                    $cutoff=InactivityPolicy::cutoff($days,new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
                    $settings=Exclusions::fromArray([
                        'protect_admins'=>$input->post->getInt('protect_admins',1),
                        'excluded_names'=>$input->post->get('excluded','', 'RAW'),
                        'excluded_groups'=>$input->post->get('excluded_groups',[], 'ARRAY'),
                    ]);
                    $includeNever=$input->post->getInt('include_never')===1;
                    $draft=$settings->toArray()+['days'=>$days,'include_never'=>(int)$includeNever];
                    $session->set('usertools.inactive.draft',$draft);
                    $params=ComponentHelper::getParams('com_usertools');
                    $params->set('manual_inactivity',$draft);
                    $db=Factory::getContainer()->get(DatabaseInterface::class);
                    $db->setQuery($db->createQuery()->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('params').' = '.$db->quote((string)$params))
                        ->where($db->quoteName('element').' = '.$db->quote('com_usertools'))
                        ->where($db->quoteName('type').' = '.$db->quote('component')))->execute();
                    $candidates=$this->candidates($settings,$cutoff,$includeNever,(int)$actor->id);
                    $session->set('usertools.inactive.preview',[
                        'settings'=>$draft,'cutoff'=>$cutoff->format('Y-m-d H:i:s'),
                        'ids'=>array_map(fn($u)=>(int)$u->id,$candidates),'selected'=>$preview['selected']??[],
                        'expires'=>time()+900,'actor'=>(int)$actor->id,'nonce'=>bin2hex(random_bytes(24)),
                    ]);
                    return $this->go('review');
                }
                if (in_array($operation,['back','suspend'],true)) {
                    if (!$preview || !hash_equals($preview['nonce'],$input->post->getString('preview'))) {
                        throw new \InvalidArgumentException('La revisión ha caducado. Vuelve a buscar las cuentas inactivas.');
                    }
                    if ($input->post->getInt('selection_ready')!==1) {
                        throw new \InvalidArgumentException('Activa JavaScript para seleccionar las cuentas.');
                    }
                    $selected=InactiveSelection::decode($input->post->get('selected','[]','RAW'),$preview['ids']);
                    $preview['selected']=$selected;
                    $session->set('usertools.inactive.preview',$preview);
                    if ($operation==='back') { return $this->go('setup'); }
                    if (!$selected || $input->post->getInt('confirm')!==1) {
                        throw new \InvalidArgumentException('Marca al menos una cuenta y confirma la suspensión.');
                    }
                    $session->set('usertools.inactive.preview',[]);
                    $settings=Exclusions::fromArray($preview['settings']);
                    $cutoff=new \DateTimeImmutable($preview['cutoff'],new \DateTimeZone('UTC'));
                    $fresh=$this->candidates($settings,$cutoff,(bool)$preview['settings']['include_never'],(int)$actor->id);
                    $eligible=InactiveSelection::eligibleIds($selected,$preview['ids'],array_map(fn($u)=>(int)$u->id,$fresh));
                    $result=['blocked'=>[],'errors'=>[],'skipped'=>count($selected)-count($eligible),'actor'=>(int)$actor->id,'expires'=>time()+900];
                    $factory=Factory::getContainer()->get(UserFactoryInterface::class);
                    foreach ($eligible as $id) {
                        $user=$factory->loadUserById($id);
                        if ($settings->protects($user,(int)$actor->id)
                            || !InactivityPolicy::eligible($user,$cutoff,(bool)$preview['settings']['include_never'])
                            || $this->active($id)) { $result['skipped']++; continue; }
                        $user->block=1;
                        try {
                            if (!$user->save()) { throw new \RuntimeException('Guardado rechazado.'); }
                            $result['blocked'][]=$user->username;
                        } catch (\Throwable $e) { $result['errors'][]=$user->username; }
                    }
                    $session->set('usertools.inactive.result',$result);
                    return $this->go('result');
                }
            } catch (\InvalidArgumentException $e) {
                $app->enqueueMessage($e->getMessage(),'warning');
                return $this->go($operation!=='search' && $preview ? 'review' : 'setup');
            }
            return $this->go('setup');
        }
        $candidates=[];
        if ($stage==='review') {
            if (!$preview) { return $this->go('setup'); }
            $settings=Exclusions::fromArray($preview['settings']);
            $fresh=$this->candidates($settings,new \DateTimeImmutable($preview['cutoff'],new \DateTimeZone('UTC')),(bool)$preview['settings']['include_never'],(int)$actor->id);
            $candidates=array_filter($fresh,fn($u)=>in_array((int)$u->id,$preview['ids'],true));
        }
        $result=$session->get('usertools.inactive.result',[]);
        if ($stage==='result' && (!$result || $result['actor']!==(int)$actor->id || $result['expires']<time())) { return $this->go('setup'); }
        $db=Factory::getContainer()->get(DatabaseInterface::class);
        $groups=$db->setQuery($db->createQuery()->select($db->quoteName(['id','title']))->from($db->quoteName('#__usergroups'))->order($db->quoteName('lft')))->loadObjectList();
        foreach($groups as $group) { $group->adminProtected=Access::checkGroup((int)$group->id,'core.admin') || Access::checkGroup((int)$group->id,'core.login.admin'); }
        $app->getDocument()->addStyleSheet(Uri::root(true).'/media/com_usertools/css/admin.css');
        $app->getDocument()->addScript(Uri::root(true).'/media/com_usertools/js/admin.js',[],['defer'=>true]);
        require JPATH_ADMINISTRATOR.'/components/com_usertools/tmpl/inactive.php';
        return $this;
    }

    private function go(string $stage): self
    {
        Factory::getApplication()->redirect('index.php?option=com_usertools&task=inactive.display&stage='.$stage);
        return $this;
    }

    private function candidates(Exclusions $settings,\DateTimeImmutable $cutoff,bool $includeNever,int $actorId): array
    {
        $db=Factory::getContainer()->get(DatabaseInterface::class);
        $records=$db->setQuery($db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__users'))
            ->where($db->quoteName('block').' = 0')->order($db->quoteName('id')))->loadColumn();
        $factory=Factory::getContainer()->get(UserFactoryInterface::class);
        $candidates=[];
        foreach($records as $id) {
            $user=$factory->loadUserById((int)$id);
            if (InactivityPolicy::eligible($user,$cutoff,$includeNever) && !$settings->protects($user,$actorId) && !$this->active((int)$id)) {
                $candidates[]=$user;
            }
        }
        return $candidates;
    }

    private function active(int $id): bool
    {
        $db=Factory::getContainer()->get(DatabaseInterface::class);
        $lifetime=max(1,(int)Factory::getApplication()->get('lifetime',15))*60;
        return (int)$db->setQuery($db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__session'))
            ->where($db->quoteName('userid').' = '.$id)
            ->where($db->quoteName('time').' >= '.$db->quote((string)(time()-$lifetime))))->loadResult()>0;
    }
}
