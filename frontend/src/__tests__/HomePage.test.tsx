import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Approval, User } from '@/api';
import HomePage from '@/app/page';

const mocks = vi.hoisted(() => ({
  getApprovals: vi.fn(),
  workspace: {} as {
    currentUserId: number;
    currentUser: User | null;
    masters: { departments: Array<{ id: number; name: string }>; stores: Array<{ id: number; name: string }> };
    loadingMasters: boolean;
  },
}));

vi.mock('@/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api')>();
  return { ...actual, api: { ...actual.api, getApprovals: mocks.getApprovals } };
});

vi.mock('@/components/WorkspaceContext', () => ({
  useWorkspace: () => mocks.workspace,
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

const approval: Approval = {
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
};

function setWorkspace(currentUser: User) {
  mocks.workspace = {
    currentUserId: currentUser.id,
    currentUser,
    masters: {
      departments: [{ id: 1, name: '営業部' }],
      stores: [{ id: 2, name: '渋谷店' }],
    },
    loadingMasters: false,
  };
}

describe('HomePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setWorkspace(applicant);
    mocks.getApprovals.mockResolvedValue({ data: [approval] });
  });

  it('shows the applicant tab and updates status filters', async () => {
    const user = userEvent.setup();
    render(<HomePage />);

    expect(screen.getByRole('tab', { name: '自分の申請' })).toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('link', { name: 'モニター購入の申請' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: '承認担当者' })).toBeInTheDocument();
    await waitFor(() => expect(mocks.getApprovals).toHaveBeenCalledOnce());
    const initialQuery = mocks.getApprovals.mock.calls[0][1] as URLSearchParams;
    expect(mocks.getApprovals.mock.calls[0][0]).toBe(1);
    expect(initialQuery.get('applicant_user_id')).toBe('1');

    await user.click(screen.getByRole('tab', { name: '申請中' }));
    await waitFor(() => expect(mocks.getApprovals).toHaveBeenCalledTimes(2));
    const filteredQuery = mocks.getApprovals.mock.calls[1][1] as URLSearchParams;
    expect(filteredQuery.get('status_id')).toBe('0');
    expect(filteredQuery.get('applicant_user_id')).toBe('1');
  });

  it('requests the selected approver’s assigned queue', async () => {
    setWorkspace(approver);
    render(<HomePage />);

    expect(await screen.findByRole('heading', { name: '承認ワークベンチ' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: '他人の承認' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('columnheader', { name: '起案者' })).toBeInTheDocument();
    await waitFor(() => expect(mocks.getApprovals).toHaveBeenCalledOnce());
    const query = mocks.getApprovals.mock.calls[0][1] as URLSearchParams;
    expect(mocks.getApprovals.mock.calls[0][0]).toBe(3);
    expect(query.get('assignee_user_id')).toBe('3');
    expect(query.has('applicant_user_id')).toBe(false);
    expect(screen.queryByRole('link', { name: '新規稟議' })).not.toBeInTheDocument();
  });

  it('switches between own applications and assigned approvals', async () => {
    const user = userEvent.setup();
    render(<HomePage />);

    await screen.findByRole('link', { name: 'モニター購入の申請' });
    await user.click(screen.getByRole('tab', { name: '他人の承認' }));

    await waitFor(() => expect(mocks.getApprovals).toHaveBeenCalledTimes(2));
    const reviewQuery = mocks.getApprovals.mock.calls[1][1] as URLSearchParams;
    expect(reviewQuery.get('assignee_user_id')).toBe('1');
    expect(reviewQuery.has('applicant_user_id')).toBe(false);
    expect(screen.getByRole('tab', { name: '他人の承認' })).toHaveAttribute('aria-selected', 'true');

    await user.click(screen.getByRole('tab', { name: '自分の申請' }));
    await waitFor(() => expect(mocks.getApprovals).toHaveBeenCalledTimes(3));
    const ownQuery = mocks.getApprovals.mock.calls[2][1] as URLSearchParams;
    expect(ownQuery.get('applicant_user_id')).toBe('1');
    expect(ownQuery.has('assignee_user_id')).toBe(false);
  });
});