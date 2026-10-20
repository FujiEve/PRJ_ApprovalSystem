import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Approval, ApprovalComment, User } from '@/api';
import ApprovalDetailPage from '@/app/approvals/[id]/page';

const mocks = vi.hoisted(() => ({
  getApproval: vi.fn(),
  approve: vi.fn(),
  remand: vi.fn(),
  withdraw: vi.fn(),
  addComment: vi.fn(),
  updateCommentStatus: vi.fn(),
  push: vi.fn(),
  refresh: vi.fn(),
  workspace: {} as { currentUserId: number; currentUser: User | null },
}));

vi.mock('@/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api')>();
  return {
    ...actual,
    api: {
      ...actual.api,
      getApproval: mocks.getApproval,
      approve: mocks.approve,
      remand: mocks.remand,
      withdraw: mocks.withdraw,
      addComment: mocks.addComment,
      updateCommentStatus: mocks.updateCommentStatus,
    },
  };
});

vi.mock('@/components/WorkspaceContext', () => ({
  useWorkspace: () => mocks.workspace,
}));

vi.mock('next/navigation', () => ({
  useParams: () => ({ id: '21' }),
  useRouter: () => ({ push: mocks.push, refresh: mocks.refresh }),
}));

vi.mock('next/link', () => ({
  default: ({ href, children, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement> & { href: string }) => (
    <a href={href} {...props}>{children}</a>
  ),
}));

const applicant: User = {
  id: 1,
  name: '山田 太郎',
  email: 'yamada@example.local',
  position: '主任',
  authority: 'applicant',
  department: { id: 1, name: '営業部' },
  store: { id: 2, name: '渋谷店', prefecture_id: 13, postal_code: '150-0002', address_detail: '渋谷' },
};

const approver: User = {
  ...applicant,
  id: 3,
  name: '佐藤 健一',
  authority: 'approver',
};

const discussionComment: ApprovalComment = {
  id: 51,
  user_id: 3,
  content: '見積内容を確認してください。',
  status: 0,
  created_at: '2026-10-01T01:00:00.000000Z',
  user: approver,
};

const pendingApproval: Approval = {
  id: 21,
  applicant_user_id: 1,
  assignee_user_id: 3,
  assignee_name: '佐藤 健一',
  status_id: 0,
  subject: 'モニター購入の申請',
  amount: 42500,
  detail: 'モニターを購入します',
  reason: '故障した機材の交換です',
  created_at: '2026-10-01T00:00:00.000000Z',
  updated_at: '2026-10-01T00:00:00.000000Z',
  applicant,
  assignee: approver,
  status: { id: 0, name: '申請中' },
  comments: [discussionComment],
  histories: [],
  files: [],
};

describe('ApprovalDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.workspace = { currentUserId: 3, currentUser: approver };
    mocks.getApproval.mockResolvedValue({ data: pendingApproval });
    mocks.approve.mockResolvedValue({ data: pendingApproval, message: 'approved' });
    mocks.remand.mockResolvedValue({ data: pendingApproval, message: 'remanded' });
    mocks.withdraw.mockResolvedValue({ data: pendingApproval, message: 'withdrawn' });
  });

  it('requires a comment to remand and sends the trimmed reason', async () => {
    const user = userEvent.setup();
    render(<ApprovalDetailPage />);

    expect(await screen.findByRole('heading', { name: '承認判断' })).toBeInTheDocument();
    expect(screen.queryByLabelText('コメント状態を進める')).not.toBeInTheDocument();
    const remandButton = screen.getByRole('button', { name: '差し戻す' });
    expect(remandButton).toBeDisabled();
    expect(screen.getByRole('button', { name: '承認する' })).toBeEnabled();

    await user.type(screen.getByPlaceholderText('判断理由や修正指示'), '見積を追記してください');
    await user.click(remandButton);

    await waitFor(() => expect(mocks.remand).toHaveBeenCalledWith(3, 21, '見積を追記してください'));
    expect(mocks.getApproval).toHaveBeenCalledTimes(2);
  });

  it('shows withdrawal instead of review actions to the applicant', async () => {
    mocks.workspace = { currentUserId: 1, currentUser: applicant };
    const user = userEvent.setup();
    render(<ApprovalDetailPage />);

    expect(await screen.findByRole('button', { name: '申請を取り下げる' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '承認する' })).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: '申請を取り下げる' }));

    await waitFor(() => expect(mocks.withdraw).toHaveBeenCalledWith(1, 21));
  });

  it('allows the applicant to move an unread comment only to read', async () => {
    mocks.workspace = { currentUserId: 1, currentUser: applicant };
    const user = userEvent.setup();
    render(<ApprovalDetailPage />);

    const statusSelect = await screen.findByLabelText('コメント状態を進める');
    expect(screen.getAllByRole('option', { name: '未読（現在）' })).toHaveLength(1);
    expect(screen.getByRole('option', { name: '既読にする' })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: '対応済みにする' })).not.toBeInTheDocument();

    await user.selectOptions(statusSelect, '1');

    await waitFor(() => expect(mocks.updateCommentStatus).toHaveBeenCalledWith(1, 21, 51, 1));
  });

  it('allows the applicant to move a read comment only to resolved', async () => {
    mocks.workspace = { currentUserId: 1, currentUser: applicant };
    mocks.getApproval.mockResolvedValue({
      data: {
        ...pendingApproval,
        comments: [{ ...discussionComment, status: 1 }],
      },
    });
    const user = userEvent.setup();
    render(<ApprovalDetailPage />);

    const statusSelect = await screen.findByLabelText('コメント状態を進める');
    expect(screen.getByRole('option', { name: '既読（現在）' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: '対応済みにする' })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: '未読にする' })).not.toBeInTheDocument();

    await user.selectOptions(statusSelect, '2');

    await waitFor(() => expect(mocks.updateCommentStatus).toHaveBeenCalledWith(1, 21, 51, 2));
  });
});