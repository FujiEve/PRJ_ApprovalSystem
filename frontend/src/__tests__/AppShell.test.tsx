import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Masters, User } from '@/api';
import AppShell from '@/components/AppShell';

const mocks = vi.hoisted(() => ({
  workspace: {} as {
    currentUserId: number;
    setCurrentUserId: (userId: number) => void;
    currentUser: User | null;
    masters: Masters | null;
    loadingMasters: boolean;
    mastersError: string;
  },
  pathname: '/',
}));

vi.mock('@/components/WorkspaceContext', () => ({
  WorkspaceProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useWorkspace: () => mocks.workspace,
}));

vi.mock('next/navigation', () => ({
  usePathname: () => mocks.pathname,
}));

vi.mock('next/link', () => ({
  default: ({ href, children, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement> & { href: string }) => (
    <a href={href} {...props}>{children}</a>
  ),
}));

const admin: User = {
  id: 4,
  name: '高橋 誠',
  email: 'takahashi@example.local',
  position: '店長',
  authority: 'admin',
  department: { id: 1, name: '営業部' },
  store: null,
};

describe('AppShell', () => {
  beforeEach(() => {
    mocks.pathname = '/';
    mocks.workspace = {
      currentUserId: admin.id,
      setCurrentUserId: vi.fn(),
      currentUser: admin,
      masters: { users: [admin], departments: [], stores: [], statuses: [] },
      loadingMasters: false,
      mastersError: '',
    };
  });

  it('shows the new-approval navigation for admin users', () => {
    render(<AppShell>Contents</AppShell>);

    expect(screen.getByRole('link', { name: /新規起案/ })).toHaveAttribute('href', '/approvals/new');
  });
});
