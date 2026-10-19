import Link from 'next/link';
import ApprovalForm from '@/components/ApprovalForm';

export default function NewApprovalPage() {
  return (
    <div>
      <div className="breadcrumb"><Link href="/">稟議一覧</Link><span>/</span><span>新規起案</span></div>
      <div className="page-heading">
        <div><span className="eyebrow">NEW REQUEST / DRAFT</span><h1>稟議を起案する</h1><p>必要事項を入力し、下書き保存または申請してください。</p></div>
      </div>
      <ApprovalForm />
    </div>
  );
}