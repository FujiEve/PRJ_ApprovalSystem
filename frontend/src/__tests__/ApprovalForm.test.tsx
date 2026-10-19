import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Approval, User } from '@/api';
import ApprovalForm from '@/components/ApprovalForm';

const mocks = vi.hoisted(() => ({
  createApproval: vi.fn(),
  updateApproval: vi.fn(),
  push: vi.fn(),
  refresh: vi.fn(),
  workspace: {} as {
    currentUserId: number;
    currentUser: User | null;
    masters: { users: User[] };
    loadingMasters: boolean;
  },
}));

vi.mock('@/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api')>();
  return {
    ...actual,
    api: {
      ...actual.api,
      createApproval: mocks.createApproval,
      updateApproval: mocks.updateApproval,
    },
  };
});

vi.mock('@/components/WorkspaceContext', () => ({
  useWorkspace: () => mocks.workspace,
}));

vi.mock('next/navigation', () => ({
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

function useApplicantWorkspace() {
  mocks.workspace = {
    currentUserId: 1,
    currentUser: applicant,
    masters: { users: [applicant, approver] },
    loadingMasters: false,
  };
}

describe('ApprovalForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useApplicantWorkspace();
    mocks.createApproval.mockResolvedValue({ data: { id: 44 }, message: 'saved' });
    mocks.updateApproval.mockResolvedValue({ data: { id: 12 }, message: 'updated' });
  });

  it('saves a draft with only a subject and navigates to its detail', async () => {
    const user = userEvent.setup();
    render(<ApprovalForm />);

    await user.type(screen.getByLabelText(/件名/), '備品購入の申請');
    await user.click(screen.getByRole('button', { name: '下書き保存' }));

    await waitFor(() => expect(mocks.createApproval).toHaveBeenCalledWith(1, expect.objectContaining({
      applicant_user_id: 1,
      status_id: 3,
      subject: '備品購入の申請',
      amount: 0,
      detail: '',
      reason: '',
    })));
    expect(mocks.push).toHaveBeenCalledWith('/approvals/44');
  });

  it('requires amount, detail, and reason before submitting', async () => {
    const user = userEvent.setup();
    render(<ApprovalForm />);

    await user.type(screen.getByLabelText(/件名/), '備品購入の申請');
    await user.type(screen.getByLabelText(/申請金額/), '1250');
    await user.click(screen.getByRole('button', { name: '申請する' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('申請時は詳細と申請理由の入力が必要です。');
    expect(mocks.createApproval).not.toHaveBeenCalled();
  });

  it('submits complete application data', async () => {
    const user = userEvent.setup();
    render(<ApprovalForm />);

    await user.type(screen.getByLabelText(/件名/), '備品購入の申請');
    await user.type(screen.getByLabelText(/申請金額/), '1250');
    await user.type(screen.getByLabelText(/詳細/), 'モニターを1台購入します');
    await user.type(screen.getByLabelText(/申請理由/), '故障した機材の交換です');
    await user.selectOptions(screen.getByLabelText('承認担当者'), '3');
    await user.click(screen.getByRole('button', { name: '申請する' }));

    await waitFor(() => expect(mocks.createApproval).toHaveBeenCalledWith(1, expect.objectContaining({
      applicant_user_id: 1,
      assignee_user_id: 3,
      status_id: 0,
      subject: '備品購入の申請',
      amount: 1250,
      detail: 'モニターを1台購入します',
      reason: '故障した機材の交換です',
    })));
  });

  it('blocks approver users from creating an application', () => {
    mocks.workspace = {
      ...mocks.workspace,
      currentUserId: 3,
      currentUser: approver,
    };

    render(<ApprovalForm />);

    expect(screen.getByRole('alert')).toHaveTextContent('起案者ユーザーに切り替えてください。');
    expect(screen.queryByRole('button', { name: '申請する' })).not.toBeInTheDocument();
  });

  it('updates an existing approval using the actor user id', async () => {
    const initial = {
      id: 12,
      applicant_user_id: 1,
      assignee_user_id: 0,
      assignee_name: '未定',
      status_id: 3,
      subject: '下書き',
      amount: 0,
      detail: '',
      reason: '',
      created_at: '2026-10-01T00:00:00.000000Z',
      updated_at: '2026-10-01T00:00:00.000000Z',
      applicant,
      assignee: null,
      status: { id: 3, name: '下書き' },
      files: [],
    } satisfies Approval;
    const user = userEvent.setup();
    render(<ApprovalForm initial={initial} />);

    await user.click(screen.getByRole('button', { name: '下書き保存' }));

    await waitFor(() => expect(mocks.updateApproval).toHaveBeenCalledWith(1, '12', expect.objectContaining({
      user_id: 1,
      status_id: 3,
      subject: '下書き',
    })));
  });
});