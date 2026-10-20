import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, approvalFileUrl, departmentAndStore, formatCurrency, statusLabels, userRoleLabel } from '@/api';

function mockJsonResponse(payload: unknown, ok = true, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok,
    status,
    json: vi.fn().mockResolvedValue(payload),
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('approval API client', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('includes the current-user header and filters in list requests', async () => {
    const fetchMock = mockJsonResponse({ data: [] });

    await api.getApprovals(7, new URLSearchParams({ status_id: '2', applicant_user_id: '7' }));

    expect(fetchMock).toHaveBeenCalledOnce();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/approvals?status_id=2&applicant_user_id=7');
    expect(new Headers(init.headers).get('X-Current-User-Id')).toBe('7');
    expect(new Headers(init.headers).get('Accept')).toBe('application/json');
  });

  it('sends remand as JSON with the actor and comment', async () => {
    const fetchMock = mockJsonResponse({ data: {}, message: '差し戻しました' });

    await api.remand(3, 15, '見積を追記してください');

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/approvals/15/remand');
    expect(init.method).toBe('POST');
    expect(JSON.parse(String(init.body))).toEqual({ user_id: 3, comment: '見積を追記してください' });
    expect(new Headers(init.headers).get('Content-Type')).toBe('application/json');
  });

  it('sends uploaded files as multipart data without overriding its content type', async () => {
    const fetchMock = mockJsonResponse({ data: {}, message: 'created' });
    const file = new File(['pdf'], 'estimate.pdf', { type: 'application/pdf' });

    await api.createApproval(1, {
      applicant_user_id: 1,
      assignee_user_id: 3,
      status_id: 3,
      subject: '添付テスト',
      files: [file],
    });

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/approvals');
    expect(init.body).toBeInstanceOf(FormData);
    expect(new Headers(init.headers).get('Content-Type')).toBeNull();
    expect((init.body as FormData).getAll('files[]')).toEqual([file]);
  });

  it('sends retained and removed attachment state with an edit request', async () => {
    const fetchMock = mockJsonResponse({ data: {}, message: 'updated' });
    const file = new File(['new pdf'], 'new.pdf', { type: 'application/pdf' });

    await api.updateApproval(1, '12', {
      user_id: 1,
      assignee_user_id: 3,
      status_id: 3,
      subject: '編集済み',
      files: [file],
      existing_file_ids: [71],
      sync_existing_files: true,
    });

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const body = init.body as FormData;
    expect(url).toBe('/api/approvals/12');
    expect(init.method).toBe('POST');
    expect(body.get('_method')).toBe('PUT');
    expect(body.get('existing_file_ids[]')).toBe('71');
    expect(body.get('sync_existing_files')).toBe('1');
    expect(body.getAll('files[]')).toEqual([file]);
    expect(new Headers(init.headers).get('Content-Type')).toBeNull();
  });

  it('preserves Laravel validation details in ApiError', async () => {
    mockJsonResponse({
      message: '入力内容を確認してください。',
      errors: { subject: ['件名は必須です。'] },
    }, false, 422);

    await expect(api.createApproval(1, {
      applicant_user_id: 1,
      assignee_user_id: 0,
      status_id: 3,
      subject: '',
    })).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
      message: '入力内容を確認してください。',
      errors: { subject: ['件名は必須です。'] },
    });
  });

  it('formats status labels, departments, and yen values', () => {
    expect(statusLabels[2]).toBe('差し戻し');
    expect(departmentAndStore({
      department: { id: 1, name: '営業部' },
      store: { id: 2, name: '渋谷店' },
    } as never)).toBe('営業部 / 渋谷店');
    expect(departmentAndStore(null)).toBe('所属未設定');
    expect(formatCurrency(1250)).toContain('1,250');
  });

  it('uses the requested labels for operation-user roles', () => {
    expect(userRoleLabel({ authority: 'applicant' })).toBe('一般ユーザ');
    expect(userRoleLabel({ authority: 'approver' })).toBe('管理ユーザ');
    expect(userRoleLabel({ authority: 'admin' })).toBe('管理ユーザ');
    expect(approvalFileUrl(12, 34, 1)).toBe('/api/approvals/12/files/34?current_user_id=1');
  });
});