'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api, approvalFileUrl, departmentAndStore, filePreviewKind, formatCurrency, formatDate, statusLabels, type Approval } from '@/api';
import { useWorkspace } from '@/components/WorkspaceContext';

const commentStatuses = ['未読', '既読', '対応済み'];

function formatTimestamp(value: string): string {
  return new Intl.DateTimeFormat('ja-JP', {
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

export default function ApprovalDetailPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const { currentUser, currentUserId } = useWorkspace();
  const [approval, setApproval] = useState<Approval | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionComment, setActionComment] = useState('');
  const [newComment, setNewComment] = useState('');
  const [busy, setBusy] = useState(false);
  const [previewFileId, setPreviewFileId] = useState<number | null>(null);

  useEffect(() => {
    if (previewFileId === null) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setPreviewFileId(null);
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [previewFileId]);
  useEffect(() => {
    if (!currentUser) return;
    let active = true;
    setLoading(true);
    api.getApproval(currentUserId, id)
      .then(({ data }) => {
        if (active) {
          setApproval(data);
          setError('');
        }
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof Error ? requestError.message : '稟議を取得できませんでした。');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => { active = false; };
  }, [currentUser, currentUserId, id]);

  async function refreshApproval() {
    const { data } = await api.getApproval(currentUserId, id);
    setApproval(data);
  }

  async function runAction(action: 'approve' | 'remand' | 'withdraw') {
    if (!approval) return;
    if (action === 'remand' && !actionComment.trim()) {
      setError('差し戻し理由を入力してください。');
      return;
    }
    setBusy(true);
    setError('');
    try {
      if (action === 'approve') await api.approve(currentUserId, approval.id, actionComment);
      if (action === 'remand') await api.remand(currentUserId, approval.id, actionComment);
      if (action === 'withdraw') await api.withdraw(currentUserId, approval.id);
      setActionComment('');
      await refreshApproval();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : '操作を完了できませんでした。');
    } finally {
      setBusy(false);
    }
  }

  async function postComment() {
    if (!approval || !newComment.trim()) return;
    setBusy(true);
    setError('');
    try {
      await api.addComment(currentUserId, approval.id, newComment.trim());
      setNewComment('');
      await refreshApproval();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'コメントを投稿できませんでした。');
    } finally {
      setBusy(false);
    }
  }

  async function changeCommentStatus(commentId: number, status: number) {
    if (!approval) return;
    setBusy(true);
    setError('');
    try {
      await api.updateCommentStatus(currentUserId, approval.id, commentId, status);
      await refreshApproval();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'コメント状態を更新できませんでした。');
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <div className="loading-state">稟議を読み込んでいます</div>;
  if (error && !approval) return <div className="error-banner" role="alert">{error}<p><Link href="/">稟議一覧へ戻る</Link></p></div>;
  if (!approval) return null;

  const isApplicant = approval.applicant_user_id === currentUserId;
  const isApprover = currentUser?.authority === 'approver' || currentUser?.authority === 'admin';
  const canEdit = isApplicant && [2, 3].includes(approval.status_id);
  const canWithdraw = isApplicant && approval.status_id === 0;
  const canReview = isApprover
    && approval.status_id === 0
    && (currentUser?.authority === 'admin' || approval.assignee_user_id === currentUserId);

  return (
    <div>
      <div className="breadcrumb"><Link href="/">稟議一覧</Link><span>/</span><span>稟議 #{approval.id}</span></div>
      <div className="page-heading detail-heading">
        <div>
          <span className="eyebrow">APPROVAL REQUEST / #{approval.id}</span>
          <h1>{approval.subject}</h1>
          <p>{departmentAndStore(approval.applicant)} ・ 起案日 {formatDate(approval.created_at)}</p>
        </div>
        <span className={`status-badge status-${approval.status_id}`}>{approval.status?.name ?? statusLabels[approval.status_id]}</span>
      </div>

      {error && <div className="error-banner detail-error" role="alert">{error}</div>}

      <div className="detail-layout">
        <div className="detail-main-column">
          <section className="surface detail-panel">
            <div className="detail-panel-heading"><span>REQUEST SUMMARY</span><span>#{approval.id}</span></div>
            <div className="detail-facts">
              <div><span>起案者</span><strong>{approval.applicant?.name ?? '不明'}</strong><small>{approval.applicant?.position ?? ''}</small></div>
              <div><span>承認担当者</span><strong>{approval.assignee_name || approval.assignee?.name || '未定'}</strong><small>{approval.assignee?.position ?? ''}</small></div>
              <div><span>所属</span><strong>{departmentAndStore(approval.applicant)}</strong></div>
              <div><span>申請金額</span><strong className="detail-amount">{formatCurrency(approval.amount)}</strong></div>
            </div>
            <div className="detail-copy">
              <div><h2>申請内容</h2><p>{approval.detail || '詳細は未入力です。'}</p></div>
              <div><h2>申請理由</h2><p>{approval.reason || '理由は未入力です。'}</p></div>
            </div>
            <div className="detail-attachments">
              <h2>添付資料 <span>{approval.files?.length ?? 0}</span></h2>
              {approval.files?.length ? (
                <>
                  <ul>{approval.files.map((file) => {
                    const stored = file.file_url.startsWith('approvals/');
                    const kind = stored ? filePreviewKind(file.file_name) : null;
                    return (
                      <li key={file.id}>
                        <a href={stored ? approvalFileUrl(approval.id, file.id, currentUserId) : file.file_url} target="_blank" rel="noreferrer">{file.file_name}<span aria-hidden="true"> ↗</span></a>
                        {kind && (
                          <button type="button" className="preview-toggle" onClick={() => setPreviewFileId(file.id)}>
                            プレビュー
                          </button>
                        )}
                      </li>
                    );
                  })}</ul>
                  {approval.files.filter((file) => file.id === previewFileId).map((file) => (
                    <div className="preview-overlay" key={file.id} role="dialog" aria-modal="true" aria-label={`${file.file_name}のプレビュー`} onClick={() => setPreviewFileId(null)}>
                      <div className="attachment-preview" onClick={(event) => event.stopPropagation()}>
                        <header><strong>{file.file_name}</strong><button type="button" onClick={() => setPreviewFileId(null)} aria-label="プレビューを閉じる">閉じる ✕</button></header>
                        {filePreviewKind(file.file_name) === 'image'
                          // eslint-disable-next-line @next/next/no-img-element
                          ? <img src={approvalFileUrl(approval.id, file.id, currentUserId, true)} alt={file.file_name} />
                          : <iframe src={approvalFileUrl(approval.id, file.id, currentUserId, true)} title={file.file_name} />}
                      </div>
                    </div>
                  ))}
                </>
              ) : <p>添付資料はありません。</p>}
            </div>
          </section>

          <section className="surface comment-panel">
            <div className="section-heading-row"><div><span className="eyebrow">DISCUSSION</span><h2>コメント</h2></div><span className="count-label">{approval.comments?.length ?? 0} 件</span></div>
            <form className="comment-composer" onSubmit={(event) => { event.preventDefault(); void postComment(); }}>
              <label className="sr-only" htmlFor="new-comment">コメントを入力</label>
              <textarea id="new-comment" className="field-control" rows={3} maxLength={2000} value={newComment} onChange={(event) => setNewComment(event.target.value)} placeholder="確認事項や補足を入力" />
              <div className="composer-footer"><span>{currentUser?.name ?? 'ユーザー未選択'}</span><button className="button button-primary button-small" type="submit" disabled={busy || !newComment.trim()}>コメントを投稿</button></div>
            </form>
            <div className="comment-list">
              {approval.comments?.map((comment) => (
                <article className="comment-item" key={comment.id}>
                  <div className="comment-avatar">{comment.user?.name?.slice(0, 1) ?? '?'}</div>
                  <div className="comment-content">
                    <header><strong>{comment.user?.name ?? 'ユーザー'}</strong><time>{formatTimestamp(comment.created_at)}</time></header>
                    <p>{comment.content}</p>
                    <div className="comment-status-row">
                      <span className={`comment-state comment-state-${comment.status}`}>{commentStatuses[comment.status] ?? '未読'}</span>
                      {isApplicant && <label>
                        <span className="sr-only">コメント状態を進める</span>
                        <select value={comment.status} disabled={busy || comment.status === 2} onChange={(event) => void changeCommentStatus(comment.id, Number(event.target.value))}>
                          <option value={comment.status}>{commentStatuses[comment.status]}（現在）</option>
                          {comment.status < 2 && <option value={comment.status + 1}>{commentStatuses[comment.status + 1]}にする</option>}
                        </select>
                      </label>}
                    </div>
                  </div>
                </article>
              ))}
              {!approval.comments?.length && <p className="muted empty-inline">コメントはまだありません。</p>}
            </div>
          </section>

          <section className="history-section">
            <div className="section-heading-row"><div><span className="eyebrow">AUDIT TRAIL</span><h2>更新履歴</h2></div></div>
            <ol className="history-list">
              {approval.histories?.map((history) => (
                <li key={history.id}>
                  <span className="history-marker" />
                  <div><strong>{history.operation_detail}</strong><span>{history.user?.name ?? 'システム'} ・ {formatTimestamp(history.created_at)}</span></div>
                </li>
              ))}
              {!approval.histories?.length && <li className="muted">履歴はありません。</li>}
            </ol>
          </section>
        </div>

        <aside className="detail-actions-column">
          <section className="surface action-panel">
            <span className="eyebrow">AVAILABLE ACTIONS</span>
            {canEdit && <>
              <h2>{approval.status_id === 2 ? '修正して再申請' : '一般ユーザの操作'}</h2>
              <Link className="button button-outline action-full" href={`/approvals/${approval.id}/edit`}>内容を編集</Link>
              <button className="button button-primary action-full" type="button" disabled={busy} onClick={() => router.push(`/approvals/${approval.id}/edit`)}>{approval.status_id === 2 ? '再申請内容を入力' : '申請内容を入力'}</button>
            </>}
            {canWithdraw && <>
              <h2>申請中</h2>
              <p>取り下げると下書きに戻り、編集できるようになります。</p>
              <button className="button button-outline action-full" type="button" disabled={busy} onClick={() => void runAction('withdraw')}>申請を取り下げる</button>
            </>}
            {canReview && <>
              <h2>承認判断</h2>
              <label className="form-field action-comment-field">
                <span>コメント <small>承認時は任意、差し戻し時は必須</small></span>
                <textarea className="field-control" rows={4} maxLength={2000} value={actionComment} onChange={(event) => setActionComment(event.target.value)} placeholder="判断理由や修正指示" />
              </label>
              <button className="button button-approve action-full" type="button" disabled={busy} onClick={() => void runAction('approve')}>承認する</button>
              <button className="button button-danger action-full" type="button" disabled={busy || !actionComment.trim()} onClick={() => void runAction('remand')}>差し戻す</button>
            </>}
            {!canEdit && !canWithdraw && !canReview && <>
              <h2>閲覧モード</h2>
              <p>{approval.status_id === 1 ? '承認済みの稟議は編集できません。' : '現在のユーザーに実行できる操作はありません。'}</p>
            </>}
          </section>
          <section className="workflow-note">
            <span className="eyebrow">WORKFLOW</span>
            <div className="workflow-step"><i className={approval.status_id === 3 ? 'is-current' : ''}>1</i><span>下書き</span></div>
            <div className="workflow-step"><i className={approval.status_id === 0 ? 'is-current' : ''}>2</i><span>申請中</span></div>
            <div className="workflow-step"><i className={approval.status_id === 1 ? 'is-current' : approval.status_id === 2 ? 'is-returned' : ''}>3</i><span>{approval.status_id === 2 ? '差し戻し' : '決裁'}</span></div>
          </section>
        </aside>
      </div>
    </div>
  );
}