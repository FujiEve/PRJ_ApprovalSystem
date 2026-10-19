export type UserRole = 'applicant' | 'approver' | 'admin';

export interface Department {
  id: number;
  name: string;
}

export interface Store {
  id: number;
  name: string;
  prefecture_id: number;
  postal_code: string;
  address_detail: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  position: string | null;
  authority: UserRole;
  department: Department | null;
  store: Store | null;
}

export interface ApprovalStatus {
  id: number;
  name: string;
  memo?: string | null;
}

export interface ApprovalFile {
  id: number;
  file_name: string;
  file_url: string;
}

export interface ApprovalComment {
  id: number;
  user_id: number;
  content: string;
  status: number;
  created_at: string;
  user?: User;
}

export interface ApprovalHistory {
  id: number;
  user_id: number | null;
  operation_detail: string;
  created_at: string;
  user?: User | null;
}

export interface Approval {
  id: number;
  applicant_user_id: number;
  assignee_user_id: number;
  assignee_name: string | null;
  status_id: number;
  subject: string;
  amount: number;
  detail: string;
  reason: string;
  created_at: string;
  updated_at: string;
  applicant: User;
  assignee: User | null;
  status: ApprovalStatus;
  comments?: ApprovalComment[];
  histories?: ApprovalHistory[];
  files?: ApprovalFile[];
}

export interface Masters {
  users: User[];
  departments: Department[];
  stores: Store[];
  statuses: ApprovalStatus[];
}

export interface ApprovalInput {
  applicant_user_id?: number;
  user_id?: number;
  assignee_user_id: number;
  assignee_name?: string | null;
  status_id: number;
  subject: string;
  amount?: number;
  detail?: string;
  reason?: string;
  files?: Array<{ file_name: string; file_url?: string }>;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, userId: number, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  headers.set('X-Current-User-Id', String(userId));
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(`/api${path}`, { ...init, headers });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new ApiError(
      payload.message ?? '通信に失敗しました。時間をおいて再度お試しください。',
      response.status,
      payload.errors,
    );
  }
  return payload as T;
}

export const api = {
  getMasters: (userId: number) => request<Masters>('/masters', userId),
  getApprovals: (userId: number, query: URLSearchParams) =>
    request<{ data: Approval[] }>(`/approvals?${query.toString()}`, userId),
  getApproval: (userId: number, id: string) =>
    request<{ data: Approval }>(`/approvals/${id}`, userId),
  createApproval: (userId: number, input: ApprovalInput) =>
    request<{ data: Approval; message: string }>('/approvals', userId, {
      method: 'POST',
      body: JSON.stringify(input),
    }),
  updateApproval: (userId: number, id: string, input: ApprovalInput) =>
    request<{ data: Approval; message: string }>(`/approvals/${id}`, userId, {
      method: 'PUT',
      body: JSON.stringify(input),
    }),
  withdraw: (userId: number, id: number) =>
    request<{ data: Approval; message: string }>(`/approvals/${id}/withdraw`, userId, {
      method: 'POST',
      body: JSON.stringify({ user_id: userId }),
    }),
  approve: (userId: number, id: number, comment: string) =>
    request<{ data: Approval; message: string }>(`/approvals/${id}/approve`, userId, {
      method: 'POST',
      body: JSON.stringify({ user_id: userId, comment }),
    }),
  remand: (userId: number, id: number, comment: string) =>
    request<{ data: Approval; message: string }>(`/approvals/${id}/remand`, userId, {
      method: 'POST',
      body: JSON.stringify({ user_id: userId, comment }),
    }),
  addComment: (userId: number, id: number, content: string) =>
    request<{ data: ApprovalComment; message: string }>(`/approvals/${id}/comments`, userId, {
      method: 'POST',
      body: JSON.stringify({ user_id: userId, content }),
    }),
  updateCommentStatus: (userId: number, id: number, commentId: number, status: number) =>
    request<{ data: ApprovalComment; message: string }>(
      `/approvals/${id}/comments/${commentId}/status`,
      userId,
      { method: 'PATCH', body: JSON.stringify({ status }) },
    ),
};

export const statusLabels: Record<number, string> = {
  0: '申請中',
  1: '承認済み',
  2: '差し戻し',
  3: '下書き',
};

export function departmentAndStore(user: User | null | undefined): string {
  return [user?.department?.name, user?.store?.name].filter(Boolean).join(' / ') || '所属未設定';
}

export function formatDate(value: string): string {
  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(value));
}

export function formatCurrency(value: number): string {
  return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 }).format(value);
}