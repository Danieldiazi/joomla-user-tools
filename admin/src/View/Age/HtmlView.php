<?php

namespace Danieldiazi\Component\Usertools\Administrator\View\Age;

\defined('_JEXEC') or die;

use Danieldiazi\Component\Usertools\Administrator\Model\AgeModel;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAge;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentAgeReader;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use Danieldiazi\Component\Usertools\Administrator\View\ViewTrait;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** Content by age: a table of years × kinds of item, and the list of one year. Read only. */
class HtmlView extends BaseHtmlView
{
    use ViewTrait;

    /** @var array<string, mixed> */
    public array $table = ['years' => [], 'kinds' => [], 'totals' => [], 'total' => 0];

    /** @var array{items: list<array<string, mixed>>, total: int, page: int} */
    public array $list = ['items' => [], 'total' => 0, 'page' => 1];

    /** @var list<array{id: int, title: string}> */
    public array $levels = [];

    public int $level = 0;
    public string $kind = '';
    public int $year = 0;
    public string $error = '';

    public function display($tpl = null): void
    {
        $input       = Factory::getApplication()->getInput();
        $this->level = max(0, $input->getInt('level', 0));
        /** @var AgeModel $model */
        $model = $this->getModel();
        try {
            $this->levels = $model->getLevels();
            if ($this->getLayout() === 'list') {
                $this->kind = $input->getCmd('kind', '');
                $this->year = ContentAge::year($input->getInt('year', 0));
                if ($this->year === 0 || !$this->canList($this->kind)) {
                    throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                }
                $this->list = $model->getList($this->kind, $this->year, $this->level, max(1, $input->getInt('page', 1)));
            } else {
                $this->table = $model->getYears($this->level);
            }
        } catch (UserFacingException $e) {
            $this->leave($e, 'view=age');
        } catch (NotAllowed $e) {
            throw $e;
        } catch (\Throwable) {
            $this->error = 'COM_USERTOOLS_AGE_UNREADABLE';
        }
        $this->prepareDocument('COM_USERTOOLS_AGE_TITLE');
        parent::display($tpl);
    }

    /** The items of a kind are listed only to users who can manage it in Joomla. */
    public function canList(string $kind): bool
    {
        return isset(ContentAgeReader::KINDS[$kind])
            && Factory::getApplication()->getIdentity()->authorise('core.manage', ContentAgeReader::KINDS[$kind]['asset']);
    }

    /** Title of the level being filtered, or ''. */
    public function levelTitle(): string
    {
        foreach ($this->levels as $level) {
            if ($level['id'] === $this->level) {
                return $level['title'];
            }
        }
        return '';
    }
}
