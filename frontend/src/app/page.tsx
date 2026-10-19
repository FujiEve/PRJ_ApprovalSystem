'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api, departmentAndStore, formatCurrency, formatDate, statusLabels, type Approval } from '@/api';
import { useWorkspace } from '@/components/WorkspaceContext';

const statusFilters = [
  { id: '', label: 'すべて' },
  { id: '3', label: '下書き' },
  { id: '0', label: '申請中' },
  { id: '2', label: '差し戻し' },
  { id: '1', label: '承認済み' },
];

export default function HomePage() {
  const { currentUserId, currentUser, masters, loadingMasters } = useWorkspace();
  const [approvals, setApprovals] = useState<Approval[]>([]);
  const [status, setStatus] = useState('');
  const [keyword, setKeyword] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [storeId, setStoreId] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const isApprover = currentUser?.authority === 'approver' || currentUser?.authority === 'admin';

  useEffect(() => {
    if (!currentUser) return;
    const query = new URLSearchParams();
    if (status) query.set('status_id', status);
    if (keyword.trim()) query.set('keyword', keyword.trim());
    if (departmentId) query.set('department_id', departmentId);
    if (storeId) query.set('store_id', storeId);
    if (currentUser.authority === 'applicant') query.set('applicant_user_id', String(currentUserId));
    if (isApprover && currentUser.authority !== 'admin') query.set('assignee_user_id', String(currentUserId));

    let active = true;
    setLoading(true);
    api.getApprovals(currentUserId, query)
      .then(({ data }) => {
        if (active) {
          setApprovals(data);
          setError('');
        }
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof Error ? requestError.message : '稟議一覧を取得できませんでした。');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => { active = false; };
  }, [currentUser, currentUserId, departmentId, isApprover, keyword, status, storeId]);

  const totalAmount = approvals.reduce((sum, approval) => sum + approval.amount, 0);

  return (
    <div>
      <div className="page-heading">
        <div>
          <span className="eyebrow">{isApprover ? 'REVIEW QUEUE' : 'MY REQUESTS'} / {new Date().getFullYear()}</span>
          <h1>{isApprover ? '承認ワークベンチ' : '稟議一覧'}</h1>
          <p>{isApprover ? '担当案件の確認と意思決定' : '起案した申請の進行状況'}</p>
        </div>
        {!isApprover && <Link className="button button-primary" href="/approvals/new"><span aria-hidden="true">＋</span> 新規稟議</Link>}
      </div>

      <div className="overview-strip" aria-label="一覧サマリー">
        <div><span>表示中の稟議</span><strong>{loading ? '—' : approvals.length}<small> 件</small></strong></div>
        <div><span>合計申請額</span><strong>{loading ? '—' : formatCurrency(totalAmount)}</strong></div>
        <div><span>現在のユーザー</span><strong className="overview-user">{currentUser?.name ?? (loadingMasters ? '読み込み中' : '未選択')}</strong></div>
      </div>

      <section className="surface list-surface" aria-label="稟議リスト">
        <div className="toolbar">
          <div className="filter-tabs" role="tablist" aria-label="ステータス">
            {statusFilters.map((filter) => (
              <button
                className={`filter-tab ${status === filter.id ? 'is-active' : ''}`}
                key={filter.id || 'all'}
                onClick={() => setStatus(filter.id)}
                role="tab"
                aria-selected={status === filter.id}
              >
                {filter.label}
              </button>
            ))}
          </div>
          <input
            className="search-control"
            type="search"
            aria-label="稟議を検索"
            placeholder="件名・内容・担当者を検索"
            value={keyword}
            onChange={(event) => setKeyword(event.target.value)}
          />
        </div>

        <div className="secondary-filters">
          <label>
            <span>部署</span>
            <select value={departmentId} onChange={(event) => setDepartmentId(event.target.value)}>
              <option value="">すべて</option>
              {masters?.departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
            </select>
          </label>
          <label>
            <span>店舗</span>
            <select value={storeId} onChange={(event) => setStoreId(event.target.value)}>
              <option value="">すべて</option>
              {masters?.stores.map((store) => <option key={store.id} value={store.id}>{store.name}</option>)}
            </select>
          </label>
          <span className="filter-note">{isApprover ? '担当者に設定された案件を表示' : '自分が起案した案件を表示'}</span>
        </div>

        {error && <div className="error-banner" role="alert">{error}</div>}
        {loading ? (
          <div className="loading-state">稟議を読み込んでいます</div>
        ) : approvals.length === 0 ? (
          <div className="empty-state">
            <strong>該当する稟議はありません</strong>
            <span>検索条件を変更するか、新しい稟議を作成してください。</span>
          </div>
        ) : (
          <div className="table-wrap">
            <table className="approval-table">
              <thead>
                <tr>
                  <th>状態</th>
                  <th>起案日</th>
                  <th>件名</th>
                  <th>部署 / 店舗</th>
                  <th>{isApprover ? '起案者' : '担当者'}</th>
                  <th className="amount-cell">申請額</th>
                  <th aria-label="詳細" />
                </tr>
              </thead>
              <tbody>
                {approvals.map((approval) => (
                  <tr key={approval.id}>
                    <td><span className={`status-badge status-${approval.status_id}`}>{approval.status?.name ?? statusLabels[approval.status_id]}</span></td>
                    <td className="muted">{formatDate(approval.created_at)}</td>
                    <td><Link className="approval-subject" href={`/approvals/${approval.id}`}>{approval.subject}</Link></td>
                    <td className="muted">{departmentAndStore(approval.applicant)}</td>
                    <td>{isApprover ? approval.applicant?.name : approval.assignee_name || approval.assignee?.name || '未定'}</td>
                    <td className="amount-cell">{formatCurrency(approval.amount)}</td>
                    <td><Link className="row-arrow" href={`/approvals/${approval.id}`} aria-label={`${approval.subject}の詳細`}>↗</Link></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="table-footer"><span>{currentUser?.authority === 'admin' ? '管理者表示' : isApprover ? '担当承認案件' : '起案案件'}</span><span>{approvals.length} 件</span></div>
      </section>
    </div>
  );
}

