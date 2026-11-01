<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\ActionLogger;
use Danieldiazi\Component\Usertools\Administrator\Service\ArticleReassigner;
use Danieldiazi\Component\Usertools\Administrator\Service\ContentHandover;
use Danieldiazi\Component\Usertools\Administrator\Service\Operator;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use PHPUnit\Framework\TestCase;

final class ContentHandoverTest extends TestCase
{
    public function testProtectedAccountsCannotStartAHandover(): void
    {
        $delegate = new Operator(9, false, 'delegate');
        self::assertSame('', ContentHandover::sourceError(Fixtures::user(41, 'editor'), $delegate));
        self::assertSame('COM_USERTOOLS_HANDOVER_ERROR_SOURCE_MISSING', ContentHandover::sourceError(null, $delegate));
        foreach ([Fixtures::user(9, 'self'), Fixtures::user(41, 'super', [8], true, true), Fixtures::user(41, 'manager', [6], false, true)] as $account) {
            self::assertSame('COM_USERTOOLS_HANDOVER_ERROR_SOURCE_PROTECTED', ContentHandover::sourceError($account, $delegate), $account->username);
        }
        self::assertSame('', ContentHandover::sourceError(Fixtures::user(41, 'manager', [6], false, true), new Operator(9, true, 'admin')),
            'a Super User may hand over the content of an administrator account');
    }

    public function testTheReceivingAccountMustBeAnotherActiveAccount(): void
    {
        $source = Fixtures::user(41, 'editor');
        self::assertSame('', ContentHandover::targetError($source, Fixtures::user(42, 'heir')));
        self::assertSame('COM_USERTOOLS_HANDOVER_ERROR_TARGET_MISSING', ContentHandover::targetError($source, null));
        self::assertSame('COM_USERTOOLS_HANDOVER_ERROR_TARGET_SAME', ContentHandover::targetError($source, Fixtures::user(41, 'editor')));
        self::assertSame('COM_USERTOOLS_HANDOVER_ERROR_TARGET_BLOCKED', ContentHandover::targetError($source, Fixtures::user(42, 'gone', block: 1)));
    }

    public function testTheFingerprintTracksAuthorshipDatesStateAndCategoryButNotTheCheckOut(): void
    {
        $article = ['id' => 5, 'created_by' => 41, 'modified' => '2026-01-01 10:00:00', 'checked_out' => 0, 'state' => 1, 'catid' => 2];
        $base = ContentHandover::fingerprint($article);
        self::assertSame($base, ContentHandover::fingerprint(['checked_out' => 9] + $article), 'opening the article is checked apart');
        foreach (['created_by' => 42, 'modified' => '2026-01-02 10:00:00', 'state' => 0, 'catid' => 3] as $field => $value) {
            self::assertNotSame($base, ContentHandover::fingerprint([$field => $value] + $article), $field);
        }
    }

    public function testOnlyListedEditableArticlesCanBeChosen(): void
    {
        $inventory = [['id' => 5, 'editable' => true], ['id' => 6, 'editable' => false], ['id' => 7, 'editable' => true]];
        self::assertSame([5, 7], array_column(ContentHandover::selection($inventory, [7, 5]), 'id'), 'kept in inventory order');
        foreach ([[6], [8], []] as $ids) {
            try {
                ContentHandover::selection($inventory, $ids);
                self::fail('Selection ' . json_encode($ids) . ' should be refused');
            } catch (UserFacingException $e) {
                self::assertSame($ids ? 'COM_USERTOOLS_HANDOVER_ERROR_SELECTION' : 'COM_USERTOOLS_HANDOVER_ERROR_NONE', $e->key());
            }
        }
    }

    public function testClosingSeparatesPendingNewAndUnchosenArticles(): void
    {
        self::assertSame(['pending' => [6], 'new' => [9], 'left' => [7]], ContentHandover::closing([6, 7, 9], [5, 6, 7], [5, 6]));
        self::assertSame(['pending' => [], 'new' => [], 'left' => []], ContentHandover::closing([], [5], [5]));
    }

    /** @return object a stand-in for com_content's Article table */
    private function table(array $values, bool $stores = true): object
    {
        return new class ($values, $stores) {
            public int $id = 0;
            public int $created_by = 0;
            public string $modified = '';
            public int $checked_out = 0;
            public int $state = 1;
            public int $catid = 2;
            public string $title = 'News';
            public int $stored = 0;
            public function __construct(private array $values, private bool $stores) {}
            public function load(int $id): bool
            {
                foreach ($this->values as $key => $value) {
                    $this->$key = $value;
                }
                return $this->id === $id;
            }
            public function check(): bool { return true; }
            public function store(): bool { $this->stored++; return $this->stores; }
        };
    }

    public function testTheWriterOnlyChangesTheAuthorOfTheReviewedArticleAndLogsIt(): void
    {
        $values = ['id' => 5, 'created_by' => 41, 'modified' => '2026-01-01 10:00:00', 'checked_out' => 0, 'state' => 1, 'catid' => 2];
        $fingerprint = ContentHandover::fingerprint($values);
        $table = $this->table($values);
        $logs = [];
        $writer = new ArticleReassigner(new ActionLogger(static function (array $messages, string $key) use (&$logs): void {
            $logs[] = [$key, $messages[0]['id'], $messages[0]['from'], $messages[0]['to']];
        }), static fn (): object => $table);
        $operator = new Operator(9, false, 'delegate');
        self::assertSame(ContentHandover::TRANSFERRED, $writer->reassign(5, $fingerprint, 41, 42, $operator, static fn (): bool => true));
        self::assertSame([42, 1], [$table->created_by, $table->stored]);
        self::assertSame([['COM_USERTOOLS_ACTION_LOG_ARTICLE_REASSIGNED', 5, '#41', '#42']], $logs);
    }

    public function testTheWriterSkipsAnythingThatChangedSinceTheReview(): void
    {
        $values = ['id' => 5, 'created_by' => 41, 'modified' => '2026-01-01 10:00:00', 'checked_out' => 0, 'state' => 1, 'catid' => 2];
        $fingerprint = ContentHandover::fingerprint($values);
        $operator = new Operator(9, false, 'delegate');
        $cases = [
            'another author'         => [['created_by' => 50] + $values, true],
            'edited since'           => [['modified' => '2026-02-01 10:00:00'] + $values, true],
            'open by someone else'   => [['checked_out' => 77] + $values, true],
            'not editable any more'  => [$values, false],
            'missing article'        => [['id' => 6] + $values, true],
        ];
        foreach ($cases as $name => [$current, $canEdit]) {
            $table = $this->table($current);
            $logs = 0;
            $writer = new ArticleReassigner(new ActionLogger(static function () use (&$logs): void {
                $logs++;
            }), static fn (): object => $table);
            self::assertSame(ContentHandover::CHANGED, $writer->reassign(5, $fingerprint, 41, 42, $operator, static fn (): bool => $canEdit), $name);
            self::assertSame([0, 0], [$table->stored, $logs], $name);
        }
        $own = $this->table(['checked_out' => 9] + $values);
        $writer = new ArticleReassigner(new ActionLogger(static function (): void {}), static fn (): object => $own);
        self::assertSame(ContentHandover::TRANSFERRED, $writer->reassign(5, $fingerprint, 41, 42, $operator, static fn (): bool => true),
            'an article the operator has open can be handed over');
        $failing = $this->table($values, false);
        $writer = new ArticleReassigner(new ActionLogger(static function (): void {
            throw new \LogicException('nothing is logged when the save fails');
        }), static fn (): object => $failing);
        self::assertSame(ContentHandover::ERROR, $writer->reassign(5, $fingerprint, 41, 42, $operator, static fn (): bool => true));
    }
}
