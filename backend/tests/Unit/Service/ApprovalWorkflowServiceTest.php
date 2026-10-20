<?php

namespace Tests\Unit;

use App\Models\Approval;
use App\Models\ApprovalComment;
use App\Models\ApprovalHistory;
use App\Models\ApprovalStatusMaster;
use App\Models\User;
use App\Repositories\ApprovalRepository;
use App\Repositories\MasterRepository;
use App\Services\ApprovalWorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApprovalWorkflowServiceTest extends TestCase
{
    public function test_list_approvals_delegates_filters_and_user_id(): void
    {
        $filters = ['status_id' => 2, 'keyword' => 'equipment'];
        $approvals = new Collection([new Approval()]);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('search')->with($filters, 7)->willReturn($approvals);

        $this->assertSame($approvals, $this->service($repository)->listApprovals($filters, 7));
    }

    public function test_detail_handles_missing_and_all_viewer_paths(): void
    {
        $repository = $this->createMock(ApprovalRepository::class);
        $assigneeApproval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
        $assigneeApproval->assignee_user_id = 3;
        $repository->expects($this->exactly(6))->method('findDetailById')->willReturnOnConsecutiveCalls(
            null,
            $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1),
            $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1),
            $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1),
            $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1),
            $assigneeApproval,
        );
        $service = $this->service($repository);

        $this->assertHttpException(404, fn () => $service->getApprovalDetail(10));
        $this->assertHttpException(403, fn () => $service->getApprovalDetail(10));
        $this->assertHttpException(403, fn () => $service->getApprovalDetail(10, 2));
        $this->assertSame(1, $service->getApprovalDetail(10, 1)->applicant_user_id);
        $this->assertHttpException(403, fn () => $service->getApprovalDetail(10, 2));
        $this->assertSame(ApprovalStatusMaster::STATUS_PENDING, $service->getApprovalDetail(10, 3)->status_id);
    }

    public function test_create_pending_with_explicit_assignee_name_and_files(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
        $files = [['file_name' => 'quote.pdf', 'file_url' => '/quote.pdf']];
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $attributes): bool =>
                $attributes['assignee_name'] === 'External reviewer'
                && $attributes['status_id'] === ApprovalStatusMaster::STATUS_PENDING
                && $attributes['subject'] === 'Purchase'
                && $attributes['is_deleted'] === false
            ))
            ->willReturn($approval);
        $repository->expects($this->once())->method('syncFiles')->with($approval->id, $files);
        $repository->expects($this->once())
            ->method('createHistory')
            ->with($approval->id, 1, '稟議を新規申請しました')
            ->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->with($approval)->willReturn($approval);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->never())->method('findActiveUserById');

        $result = $this->service($repository, $masterRepository)->createApproval([
            'applicant_user_id' => 1,
            'assignee_user_id' => 8,
            'assignee_name' => ' External reviewer ',
            'status_id' => ApprovalStatusMaster::STATUS_PENDING,
            'subject' => ' Purchase ',
            'amount' => 1250,
            'detail' => 'Details',
            'reason' => 'Reason',
            'files' => $files,
        ]);

        $this->assertSame($approval, $result);
    }

    public function test_create_draft_uses_defaults_and_resolves_assignee_name(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $attributes): bool =>
                $attributes['assignee_user_id'] === 8
                && $attributes['assignee_name'] === 'Known user'
                && $attributes['status_id'] === ApprovalStatusMaster::STATUS_DRAFT
                && $attributes['amount'] === 0
                && $attributes['detail'] === ''
                && $attributes['reason'] === ''
            ))
            ->willReturn($approval);
        $repository->expects($this->never())->method('syncFiles');
        $repository->expects($this->once())
            ->method('createHistory')
            ->with($approval->id, 1, '下書きを作成しました')
            ->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())
            ->method('findActiveUserById')
            ->with(8)
            ->willReturn(new User(['name' => 'Known user']));

        $this->assertSame($approval, $this->service($repository, $masterRepository)->createApproval([
            'applicant_user_id' => 1,
            'assignee_user_id' => 8,
            'subject' => ' Draft ',
            'files' => [],
        ]));
    }

    public function test_create_uses_unassigned_fallback_and_ignores_non_array_files(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $attributes): bool =>
                $attributes['assignee_user_id'] === 0
                && $attributes['assignee_name'] === '未定'
                && $attributes['status_id'] === ApprovalStatusMaster::STATUS_DRAFT
            ))
            ->willReturn($approval);
        $repository->expects($this->never())->method('syncFiles');
        $repository->expects($this->once())->method('createHistory')->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->never())->method('findActiveUserById');

        $this->service($repository, $masterRepository)->createApproval([
            'applicant_user_id' => 1,
            'subject' => 'Draft',
            'assignee_name' => '   ',
            'files' => 'not-an-array',
        ]);
    }

    public function test_create_uses_name_for_unassigned_assignee_and_missing_user_fallback(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            $approval,
            $approval,
        );
        $repository->expects($this->exactly(2))->method('createHistory')->willReturn(new ApprovalHistory());
        $repository->expects($this->exactly(2))->method('loadDetailRelations')->willReturn($approval);
        $repository->expects($this->never())->method('syncFiles');
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())->method('findActiveUserById')->with(8)->willReturn(null);
        $repository->expects($this->exactly(2))->method('create')->with($this->callback(
            static function (array $attributes): bool {
                static $call = 0;
                $expected = $call++ === 0 ? 'External person' : '未定';
                return $attributes['assignee_name'] === $expected;
            }
        ));

        $service = $this->service($repository, $masterRepository);
        $service->createApproval([
            'applicant_user_id' => 1,
            'assignee_user_id' => 0,
            'assignee_name' => ' External person ',
            'status_id' => ApprovalStatusMaster::STATUS_DRAFT,
            'subject' => 'Draft',
        ]);
        $service->createApproval([
            'applicant_user_id' => 1,
            'assignee_user_id' => 8,
            'status_id' => ApprovalStatusMaster::STATUS_DRAFT,
            'subject' => 'Draft',
        ]);
    }

    public function test_draft_detail_is_restricted_to_the_applicant(): void
    {
        $approval = new Approval(['status_id' => ApprovalStatusMaster::STATUS_DRAFT, 'applicant_user_id' => 1]);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->exactly(2))->method('findDetailById')->with(10)->willReturn($approval);
        $service = new ApprovalWorkflowService($repository, $this->createMock(MasterRepository::class));

        try {
            $service->getApprovalDetail(10, 2);
            $this->fail('Expected a forbidden exception.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame($approval, $service->getApprovalDetail(10, 1));
    }

    public function test_update_rejects_pending_approval_before_repository_write(): void
    {
        $approval = new Approval(['status_id' => ApprovalStatusMaster::STATUS_PENDING]);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
        $repository->expects($this->never())->method('update');
        $service = new ApprovalWorkflowService($repository, $this->createMock(MasterRepository::class));

        try {
            $service->updateApproval(10, [], 1);
            $this->fail('Expected a bad request exception.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

    public function test_update_rejects_missing_approved_invalid_state_other_actor_and_invalid_target(): void
    {
        $this->assertUpdateRejected(null, [], null, 404);
        $this->assertUpdateRejected($this->approval(ApprovalStatusMaster::STATUS_APPROVED, 1), [], 1, 400);
        $this->assertUpdateRejected($this->approval(ApprovalStatusMaster::STATUS_PENDING, 1), [], 1, 400);
        $this->assertUpdateRejected($this->approval(99, 1), [], 1, 400);
        $this->assertUpdateRejected($this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1), [], 2, 403);
        $this->assertUpdateRejected(
            $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1),
            ['status_id' => ApprovalStatusMaster::STATUS_APPROVED],
            1,
            400,
        );
    }

    public function test_update_reapplies_remanded_approval_and_syncs_files(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_REMANDED, 1);
        $approval->id = 31;
        $files = [['file_name' => 'revised.pdf']];
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(31)->willReturn($approval);
        $repository->expects($this->once())->method('update')->with($approval, $this->callback(
            static fn (array $attributes): bool =>
                $attributes['status_id'] === ApprovalStatusMaster::STATUS_PENDING
                && $attributes['assignee_name'] === 'Approver'
        ))->willReturn($approval);
        $repository->expects($this->once())->method('syncFiles')->with(31, $files);
        $repository->expects($this->once())
            ->method('createHistory')
            ->with(31, 1, '差し戻し案件を修正し、再申請しました')
            ->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())
            ->method('findActiveUserById')
            ->with(8)
            ->willReturn(new User(['name' => 'Approver']));

        $this->assertSame($approval, $this->service($repository, $masterRepository)->updateApproval(31, [
            'status_id' => ApprovalStatusMaster::STATUS_PENDING,
            'subject' => 'Updated',
            'assignee_user_id' => 8,
            'assignee_name' => ' ',
            'files' => $files,
        ], 1));
    }

    public function test_update_submits_draft_and_uses_applicant_for_history_when_actor_is_null(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1);
        $approval->id = 32;
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(32)->willReturn($approval);
        $repository->expects($this->once())->method('update')->with($approval, $this->callback(
            static fn (array $attributes): bool =>
                $attributes['status_id'] === ApprovalStatusMaster::STATUS_PENDING
                && $attributes['assignee_name'] === 'External'
        ))->willReturn($approval);
        $repository->expects($this->never())->method('syncFiles');
        $repository->expects($this->once())
            ->method('createHistory')
            ->with(32, 1, '下書きから本申請しました')
            ->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);

        $this->assertSame($approval, $this->service($repository)->updateApproval(32, [
            'status_id' => ApprovalStatusMaster::STATUS_PENDING,
            'subject' => 'Submitted',
            'assignee_user_id' => 0,
            'assignee_name' => ' External ',
        ]));
    }

    public function test_update_saves_draft_with_defaults_and_skips_non_array_files(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1);
        $approval->id = 33;
        $approval->assignee_user_id = 8;
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(33)->willReturn($approval);
        $repository->expects($this->once())->method('update')->with($approval, $this->callback(
            static fn (array $attributes): bool =>
                $attributes['status_id'] === ApprovalStatusMaster::STATUS_DRAFT
                && $attributes['assignee_user_id'] === 8
                && $attributes['assignee_name'] === '未定'
                && $attributes['amount'] === 0
                && $attributes['detail'] === ''
                && $attributes['reason'] === ''
        ))->willReturn($approval);
            $repository->expects($this->never())->method('syncFiles');
        $repository->expects($this->once())
            ->method('createHistory')
            ->with(33, 1, '稟議内容を更新（下書き保存）しました')
            ->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())->method('findActiveUserById')->with(8)->willReturn(null);

        $this->assertSame($approval, $this->service($repository, $masterRepository)->updateApproval(33, [
            'subject' => 'Draft saved',
            'files' => 'not-an-array',
        ], 1));
    }

    public function test_withdraw_rejects_a_different_applicant(): void
    {
        $approval = new Approval([
            'status_id' => ApprovalStatusMaster::STATUS_PENDING,
            'applicant_user_id' => 1,
        ]);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
        $repository->expects($this->never())->method('update');
        $service = new ApprovalWorkflowService($repository, $this->createMock(MasterRepository::class));

        try {
            $service->withdrawApproval(10, 2);
            $this->fail('Expected a forbidden exception.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_withdraw_rejects_missing_approved_and_non_pending_approvals(): void
    {
        foreach ([
            [null, 404],
            [$this->approval(ApprovalStatusMaster::STATUS_APPROVED, 1), 400],
            [$this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1), 400],
        ] as [$approval, $statusCode]) {
            $repository = $this->createMock(ApprovalRepository::class);
            $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
            $repository->expects($this->never())->method('update');
            $this->assertHttpException($statusCode, fn () => $this->service($repository)->withdrawApproval(10, 1));
        }
    }

    public function test_withdraw_succeeds_for_applicant_and_null_actor(): void
    {
        foreach ([1, null] as $actorUserId) {
            $approval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
            $repository = $this->createMock(ApprovalRepository::class);
            $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
            $repository->expects($this->once())->method('update')->with($approval, [
                'status_id' => ApprovalStatusMaster::STATUS_DRAFT,
            ])->willReturn($approval);
            $repository->expects($this->once())
                ->method('createHistory')
                ->with(10, 1, '申請を取り下げ（下書きへ変更）しました')
                ->willReturn(new ApprovalHistory());
            $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);

            $this->assertSame($approval, $this->service($repository)->withdrawApproval(10, $actorUserId));
        }
    }

    public function test_only_approvers_can_approve(): void
    {
        $user = new User(['authority' => 'applicant']);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())->method('findActiveUserById')->with(2)->willReturn($user);
        $approvalRepository = $this->createMock(ApprovalRepository::class);
        $approvalRepository->expects($this->never())->method('findByIdForUpdate');
        $service = new ApprovalWorkflowService($approvalRepository, $masterRepository);

        try {
            $service->approveApproval(10, 2);
            $this->fail('Expected a forbidden exception.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_approve_rejects_missing_user_missing_approval_and_non_pending_status(): void
    {
        foreach ([null, new User(['authority' => 'applicant'])] as $user) {
            $masterRepository = $this->createMock(MasterRepository::class);
            $masterRepository->expects($this->once())->method('findActiveUserById')->with(2)->willReturn($user);
            $repository = $this->createMock(ApprovalRepository::class);
            $repository->expects($this->never())->method('findByIdForUpdate');
            $this->assertHttpException(403, fn () =>
                $this->service($repository, $masterRepository)->approveApproval(10, 2)
            );
        }

        foreach ([
            [null, 404],
            [$this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1), 400],
        ] as [$approval, $statusCode]) {
            $masterRepository = $this->createMock(MasterRepository::class);
            $masterRepository->expects($this->once())
                ->method('findActiveUserById')
                ->with(2)
                ->willReturn(new User(['authority' => 'approver']));
            $repository = $this->createMock(ApprovalRepository::class);
            $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
            $repository->expects($this->never())->method('update');
            $this->assertHttpException(404 === $statusCode ? 404 : 400, fn () =>
                $this->service($repository, $masterRepository)->approveApproval(10, 2)
            );
        }
    }

    public function test_approver_and_admin_can_approve_with_or_without_comment(): void
    {
        foreach ([
            ['approver', ' Reviewed ', true],
            ['admin', null, false],
            ['admin', '   ', false],
        ] as [$authority, $comment, $hasComment]) {
            $approval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
            $repository = $this->createMock(ApprovalRepository::class);
            $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
            $repository->expects($this->once())->method('update')->with($approval, [
                'status_id' => ApprovalStatusMaster::STATUS_APPROVED,
            ])->willReturn($approval);
            if ($hasComment) {
                $repository->expects($this->once())
                    ->method('createComment')
                    ->with(10, 2, 'Reviewed', ApprovalComment::STATUS_READ)
                    ->willReturn(new ApprovalComment());
            } else {
                $repository->expects($this->never())->method('createComment');
            }
            $repository->expects($this->once())
                ->method('createHistory')
                ->with(10, 2, '稟議を承認しました')
                ->willReturn(new ApprovalHistory());
            $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);
            $masterRepository = $this->createMock(MasterRepository::class);
            $masterRepository->expects($this->once())
                ->method('findActiveUserById')
                ->with(2)
                ->willReturn(new User(['authority' => $authority]));

            $this->assertSame(
                $approval,
                $this->service($repository, $masterRepository)->approveApproval(10, 2, $comment),
            );
        }
    }

    public function test_remand_rejects_missing_and_non_pending_approval(): void
    {
        foreach ([
            [null, 404],
            [$this->approval(ApprovalStatusMaster::STATUS_DRAFT, 1), 400],
        ] as [$approval, $statusCode]) {
            $masterRepository = $this->createMock(MasterRepository::class);
            $masterRepository->expects($this->once())
                ->method('findActiveUserById')
                ->with(2)
                ->willReturn(new User(['authority' => 'approver']));
            $repository = $this->createMock(ApprovalRepository::class);
            $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
            $repository->expects($this->never())->method('update');
            $this->assertHttpException($statusCode, fn () =>
                $this->service($repository, $masterRepository)->remandApproval(10, 2, 'Reason')
            );
        }
    }

    public function test_approver_can_remand_with_trimmed_comment(): void
    {
        $approval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
        $repository->expects($this->once())->method('update')->with($approval, [
            'status_id' => ApprovalStatusMaster::STATUS_REMANDED,
        ])->willReturn($approval);
        $repository->expects($this->once())
            ->method('createComment')
            ->with(10, 2, 'Please revise', ApprovalComment::STATUS_UNREAD)
            ->willReturn(new ApprovalComment());
        $repository->expects($this->once())
            ->method('createHistory')
            ->with(10, 2, '稟議を差し戻しました')
            ->willReturn(new ApprovalHistory());
        $repository->expects($this->once())->method('loadDetailRelations')->willReturn($approval);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())
            ->method('findActiveUserById')
            ->with(2)
            ->willReturn(new User(['authority' => 'approver']));

        $this->assertSame(
            $approval,
            $this->service($repository, $masterRepository)->remandApproval(10, 2, ' Please revise '),
        );
    }

    public function test_remand_rejects_blank_comment_before_loading_approval(): void
    {
        $user = new User(['authority' => 'approver']);
        $masterRepository = $this->createMock(MasterRepository::class);
        $masterRepository->expects($this->once())->method('findActiveUserById')->with(2)->willReturn($user);
        $approvalRepository = $this->createMock(ApprovalRepository::class);
        $approvalRepository->expects($this->never())->method('findByIdForUpdate');
        $service = new ApprovalWorkflowService($approvalRepository, $masterRepository);

        try {
            $service->remandApproval(10, 2, " \t ");
            $this->fail('Expected a validation exception.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_invalid_comment_status_is_rejected(): void
    {
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->never())->method('findCommentById');
        $service = new ApprovalWorkflowService($repository, $this->createMock(MasterRepository::class));

        try {
            $service->updateCommentStatus(10, 20, 99, 1);
            $this->fail('Expected a validation exception.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_add_comment_rejects_missing_approval_and_records_trimmed_comment(): void
    {
        $missingRepository = $this->createMock(ApprovalRepository::class);
        $missingRepository->expects($this->once())->method('findById')->with(70)->willReturn(null);
        $this->assertHttpException(404, fn () =>
            $this->service($missingRepository)->addComment(70, 2, ' Question ')
        );

        $approval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
        $approval->id = 70;
        $comment = new ApprovalComment();
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findById')->with(70)->willReturn($approval);
        $repository->expects($this->once())
            ->method('createComment')
            ->with(70, 2, 'Question', ApprovalComment::STATUS_UNREAD)
            ->willReturn($comment);
        $repository->expects($this->once())
            ->method('createHistory')
            ->with(70, 2, 'コメントを投稿しました')
            ->willReturn(new ApprovalHistory());

        $this->assertSame($comment, $this->service($repository)->addComment(70, 2, ' Question '));
    }

    public function test_comment_status_rejects_missing_comment_and_updates_unread_to_read(): void
    {
        $missingRepository = $this->createMock(ApprovalRepository::class);
        $missingRepository->expects($this->once())->method('findCommentById')->with(70, 80)->willReturn(null);
        $this->assertHttpException(404, fn () =>
            $this->service($missingRepository)->updateCommentStatus(70, 80, ApprovalComment::STATUS_READ, 1)
        );

        $approval = $this->approval(ApprovalStatusMaster::STATUS_PENDING, 1);
        $comment = new ApprovalComment(['status' => ApprovalComment::STATUS_UNREAD]);
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findCommentById')->with(70, 80)->willReturn($comment);
        $repository->expects($this->once())->method('findById')->with(70)->willReturn($approval);
        $repository->expects($this->once())
            ->method('updateCommentStatus')
            ->with($comment, ApprovalComment::STATUS_READ)
            ->willReturn($comment);

        $this->assertSame(
            $comment,
            $this->service($repository)->updateCommentStatus(70, 80, ApprovalComment::STATUS_READ, 1),
        );
    }

    private function assertUpdateRejected(?Approval $approval, array $data, ?int $actorUserId, int $statusCode): void
    {
        $repository = $this->createMock(ApprovalRepository::class);
        $repository->expects($this->once())->method('findByIdForUpdate')->with(10)->willReturn($approval);
        $repository->expects($this->never())->method('update');

        $this->assertHttpException($statusCode, fn () =>
            $this->service($repository)->updateApproval(10, $data, $actorUserId)
        );
    }

    private function service(
        ApprovalRepository $approvalRepository,
        ?MasterRepository $masterRepository = null,
    ): ApprovalWorkflowService {
        return new ApprovalWorkflowService(
            $approvalRepository,
            $masterRepository ?? $this->createMock(MasterRepository::class),
        );
    }

    private function approval(int $status, int $applicantUserId): Approval
    {
        $approval = new Approval([
            'status_id' => $status,
            'applicant_user_id' => $applicantUserId,
            'assignee_user_id' => 0,
        ]);
        $approval->id = 10;

        return $approval;
    }

    private function assertHttpException(int $statusCode, callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $exception) {
            $this->assertSame($statusCode, $exception->getStatusCode());
            return;
        }

        $this->fail("Expected HTTP {$statusCode} exception.");
    }
}