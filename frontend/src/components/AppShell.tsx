'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { departmentAndStore } from '@/api';
import { WorkspaceProvider, useWorkspace } from './WorkspaceContext';

function ShellContent({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const { currentUserId, setCurrentUserId, currentUser, masters, loadingMasters, mastersError } = useWorkspace();
  const isApprover = currentUser?.authority === 'approver' || currentUser?.authority === 'admin';

  return (
    <div className="workspace">
      <header className="topbar">
        <Link className="brand" href="/" aria-label="稟議一覧へ">
          <span className="brand-mark">R</span>
          <span><strong>RINGI</strong><small>承認ワークスペース</small></span>
        </Link>
        <div className="workspace-context">
          <span className="context-label">操作ユーザー</span>
          <label className="user-select-wrap">
            <span className="sr-only">操作ユーザーを切り替え</span>
            <select
              value={currentUserId}
              onChange={(event) => setCurrentUserId(Number(event.target.value))}
              disabled={!masters?.users.length}
            >
              {masters?.users.map((user) => (
                <option key={user.id} value={user.id}>{user.name} / {user.authority === 'applicant' ? '起案者' : user.authority === 'admin' ? '管理者' : '承認者'}</option>
              ))}
            </select>
          </label>
          <span className={`role-dot ${isApprover ? 'is-approver' : ''}`} aria-hidden="true" />
        </div>
      </header>

      <div className="workspace-body">
        <aside className="sidebar">
          <div className="sidebar-caption">WORKSPACE</div>
          <nav className="primary-nav" aria-label="メインナビゲーション">
            <Link className={`nav-link ${pathname === '/' ? 'is-active' : ''}`} href="/">
              <span className="nav-index">01</span><span>稟議一覧</span>
            </Link>
            {!isApprover && <Link className={`nav-link ${pathname === '/approvals/new' ? 'is-active' : ''}`} href="/approvals/new">
              <span className="nav-index">02</span><span>新規起案</span>
            </Link>}
          </nav>
          <div className="sidebar-rule" />
          <div className="sidebar-caption">CURRENT USER</div>
          <div className="user-summary">
            <span className={`avatar ${isApprover ? 'avatar-approver' : ''}`}>{currentUser?.name?.slice(0, 1) ?? '?'}</span>
            <span className="user-summary-copy">
              <strong>{currentUser?.name ?? (loadingMasters ? '読み込み中' : '未選択')}</strong>
              <small>{currentUser ? departmentAndStore(currentUser) : 'ユーザーを選択'}</small>
            </span>
          </div>
          <div className="sidebar-bottom">
            <span className={`online-indicator ${mastersError ? 'is-offline' : ''}`} /> {loadingMasters ? '接続中' : mastersError ? 'API未接続' : 'API接続'}
          </div>
        </aside>

        <main className="main-content">
          {mastersError && <div className="global-error" role="alert">{mastersError} APIサーバーの起動状態を確認してください。</div>}
          {children}
        </main>
      </div>
    </div>
  );
}

export default function AppShell({ children }: { children: React.ReactNode }) {
  return (
    <WorkspaceProvider>
      <ShellContent>{children}</ShellContent>
    </WorkspaceProvider>
  );
}