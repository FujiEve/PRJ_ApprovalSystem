'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api, type Approval } from '@/api';
import ApprovalForm from '@/components/ApprovalForm';
import { useWorkspace } from '@/components/WorkspaceContext';

export default function EditApprovalPage() {
  const { id } = useParams<{ id: string }>();
  const { currentUserId, currentUser } = useWorkspace();
  const [approval, setApproval] = useState<Approval | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!currentUser) return;
    let active = true;
    api.getApproval(currentUserId, id)
      .then(({ data }) => {
        if (active) setApproval(data);
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof Error ? requestError.message : '稟議を取得できませんでした。');
      });
    return () => { active = false; };
  }, [currentUser, currentUserId, id]);

  if (error) return <div className="error-banner" role="alert">{error}</div>;
  if (!approval) return <div className="loading-state">稟議を読み込んでいます</div>;
  if (approval.applicant_user_id !== currentUserId || ![2, 3].includes(approval.status_id)) {
    return <div className="error-banner" role="alert">この稟議は編集できません。<Link href={`/approvals/${id}`}>詳細へ戻る</Link></div>;
  }

  return (
    <div>
      <div className="breadcrumb"><Link href="/">稟議一覧</Link><span>/</span><Link href={`/approvals/${id}`}>稟議詳細</Link><span>/</span><span>編集</span></div>
      <div className="page-heading">
        <div><span className="eyebrow">EDIT REQUEST / #{approval.id}</span><h1>{approval.status_id === 2 ? '差し戻し内容を修正' : '下書きを編集'}</h1><p>内容を更新して再申請、または下書き保存できます。</p></div>
      </div>
      <ApprovalForm key={`${approval.id}-${approval.updated_at}`} initial={approval} />
    </div>
  );
}