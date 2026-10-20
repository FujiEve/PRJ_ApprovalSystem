'use client';

import Link from 'next/link';
import { useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type Approval, type ApprovalInput } from '@/api';
import { useWorkspace } from './WorkspaceContext';

interface ApprovalFormProps {
  initial?: Approval;
}

export default function ApprovalForm({ initial }: ApprovalFormProps) {
  const router = useRouter();
  const formRef = useRef<HTMLFormElement>(null);
  const { currentUser, currentUserId, masters, loadingMasters } = useWorkspace();
  const [subject, setSubject] = useState(initial?.subject ?? '');
  const [assigneeId, setAssigneeId] = useState(initial?.assignee_user_id ?? 0);
  const [assigneeName, setAssigneeName] = useState(initial?.assignee_name ?? '');
  const [amount, setAmount] = useState(initial ? String(initial.amount) : '');
  const [detail, setDetail] = useState(initial?.detail ?? '');
  const [reason, setReason] = useState(initial?.reason ?? '');
  const [files, setFiles] = useState<Array<{ file_name: string; file_url: string }>>(
    initial?.files?.map(({ file_name, file_url }) => ({ file_name, file_url })) ?? [],
  );
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [fileName, setFileName] = useState('');
  const [fileUrl, setFileUrl] = useState('');
  const isEditing = Boolean(initial);
  const isRemanded = initial?.status_id === 2;

  async function save(statusId: number) {
    if (!formRef.current?.reportValidity()) return;
    setError('');
    if (statusId === 0) {
      if (!amount || !Number.isInteger(Number(amount)) || Number(amount) < 0) {
        setError('申請時は0円以上の整数金額を入力してください。');
        return;
      }
      if (!detail.trim() || !reason.trim()) {
        setError('申請時は詳細と申請理由の入力が必要です。');
        return;
      }
    }

    const payload: ApprovalInput = {
      ...(isEditing ? { user_id: currentUserId } : { applicant_user_id: currentUserId }),
      assignee_user_id: assigneeId,
      assignee_name: assigneeName.trim() || null,
      status_id: statusId,
      subject: subject.trim(),
      amount: amount ? Number(amount) : 0,
      detail,
      reason,
      files,
    };

    setSaving(true);
    try {
      const result = isEditing
        ? await api.updateApproval(currentUserId, String(initial?.id), payload)
        : await api.createApproval(currentUserId, payload);
      router.push(`/approvals/${result.data.id}`);
      router.refresh();
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : '保存できませんでした。入力内容を確認してください。');
    } finally {
      setSaving(false);
    }
  }

  function addFile() {
    if (!fileName.trim()) return;
    setFiles((current) => [...current, { file_name: fileName.trim(), file_url: fileUrl.trim() }]);
    setFileName('');
    setFileUrl('');
  }

  if (loadingMasters) return <div className="loading-state">初期データを読み込んでいます</div>;
  if (!currentUser || currentUser.authority !== 'applicant') {
    return <div className="error-banner" role="alert">一般ユーザに切り替えてください。</div>;
  }

  return (
    <div className="form-layout">
      <form className="surface approval-form" ref={formRef} onSubmit={(event) => event.preventDefault()}>
        <div className="form-section">
          <div className="form-section-heading"><span>01</span><h2>起案情報</h2></div>
          <div className="identity-row">
            <div><span>起案者</span><strong>{currentUser.name}</strong></div>
            <div><span>所属</span><strong>{currentUser.department?.name ?? '部署未設定'}{currentUser.store ? ` / ${currentUser.store.name}` : ''}</strong></div>
            <div><span>起案日</span><strong>{new Intl.DateTimeFormat('ja-JP').format(new Date())}</strong></div>
          </div>
          <div className="form-grid">
            <label className="form-field">
              <span>件名 <i>必須</i></span>
              <input className="field-control" maxLength={255} required value={subject} onChange={(event) => setSubject(event.target.value)} placeholder="申請内容が分かる件名" />
            </label>
            <label className="form-field">
              <span>申請金額 <small>円</small></span>
              <input className="field-control" type="number" min="0" step="1" inputMode="numeric" value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="0" />
            </label>
          </div>
          <div className="form-grid assignee-grid">
            <label className="form-field">
              <span>承認担当者</span>
              <select className="field-control" value={assigneeId} onChange={(event) => setAssigneeId(Number(event.target.value))}>
                <option value={0}>未定・外部</option>
                {masters?.users.filter((user) => user.authority === 'approver' || user.authority === 'admin').map((user) => (
                  <option key={user.id} value={user.id}>{user.name} / {user.position ?? user.department?.name ?? '管理ユーザ'}</option>
                ))}
              </select>
            </label>
            <label className="form-field">
              <span>担当者名 <small>未定・外部の場合</small></span>
              <input className="field-control" maxLength={100} value={assigneeName} onChange={(event) => setAssigneeName(event.target.value)} placeholder="未定 または外部担当者名" />
            </label>
          </div>
        </div>

        <div className="form-section">
          <div className="form-section-heading"><span>02</span><h2>申請内容</h2></div>
          <label className="form-field">
            <span>詳細 <i>申請時必須</i></span>
            <textarea className="field-control textarea-control" rows={5} value={detail} onChange={(event) => setDetail(event.target.value)} placeholder="購入物・契約条件・実施内容など" />
          </label>
          <label className="form-field">
            <span>申請理由 <i>申請時必須</i></span>
            <textarea className="field-control textarea-control" rows={4} value={reason} onChange={(event) => setReason(event.target.value)} placeholder="必要性や期待する効果" />
          </label>
        </div>

        <div className="form-section">
          <div className="form-section-heading"><span>03</span><h2>添付資料</h2></div>
          <div className="attachment-entry">
            <label className="form-field">
              <span>ファイル名</span>
              <input className="field-control" value={fileName} onChange={(event) => setFileName(event.target.value)} placeholder="見積書.pdf" />
            </label>
            <label className="form-field">
              <span>保存先URL</span>
              <input className="field-control" type="url" value={fileUrl} onChange={(event) => setFileUrl(event.target.value)} placeholder="https://..." />
            </label>
            <button className="button button-outline" type="button" onClick={addFile} disabled={!fileName.trim()}>追加</button>
          </div>
          {files.length > 0 && (
            <ul className="attachment-list">
              {files.map((file, index) => (
                <li key={`${file.file_name}-${index}`}>
                  <span aria-hidden="true">↗</span>
                  <span>{file.file_name}<small>{file.file_url || '保存先URL未指定'}</small></span>
                  <button type="button" aria-label={`${file.file_name}を削除`} onClick={() => setFiles((current) => current.filter((_, fileIndex) => fileIndex !== index))}>削除</button>
                </li>
              ))}
            </ul>
          )}
          <p className="field-note">現在はファイル本体ではなく、ファイル名と保存先URLを登録します。</p>
        </div>

        {error && <div className="error-banner form-error" role="alert">{error}</div>}
        <div className="form-actions">
          <Link className="button button-outline" href={isEditing ? `/approvals/${initial?.id}` : '/'}>キャンセル</Link>
          <div>
            <button className="button button-outline" type="button" disabled={saving} onClick={() => save(3)}>下書き保存</button>
            <button className="button button-primary" type="button" disabled={saving} onClick={() => save(0)}>{saving ? '保存中…' : isRemanded ? '再申請する' : '申請する'}</button>
          </div>
        </div>
      </form>
      <aside className="form-aside">
        <span className="eyebrow">SUBMISSION GUIDE</span>
        <h2>{isRemanded ? '修正して再申請' : '申請前の確認'}</h2>
        <p>下書きは件名のみで保存できます。申請時には金額・詳細・理由を入力してください。</p>
        {initial?.status_id === 2 && <div className="remand-note"><strong>差し戻し中</strong><span>指摘内容を確認し、該当箇所を修正してください。</span></div>}
      </aside>
    </div>
  );
}