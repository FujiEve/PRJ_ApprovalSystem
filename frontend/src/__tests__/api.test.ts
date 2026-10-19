import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, departmentAndStore, formatCurrency, statusLabels } from '@/api';

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
});