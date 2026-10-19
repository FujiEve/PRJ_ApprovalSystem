<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1. 稟議ステータスマスタ (approval_statuses_master)
        $statuses = [
            ['id' => 0, 'name' => '申請中', 'memo' => '起案者が申請し、承認者の判断を待っている状態'],
            ['id' => 1, 'name' => '承認済み', 'memo' => '承認者によって承認が完了した最終状態（起案者の編集ロック）'],
            ['id' => 2, 'name' => '差し戻し', 'memo' => '承認者から修正・再検討を求められた状態（編集・再申請可能）'],
            ['id' => 3, 'name' => '下書き', 'memo' => '起案者が申請前に一時保存した（または申請を取り下げた）状態'],
        ];

        foreach ($statuses as $status) {
            DB::table('approval_statuses_master')->updateOrInsert(
                ['id' => $status['id']],
                $status
            );
        }

        // 2. 部署マスタ (departments)
        $departments = [
            ['id' => 1, 'name' => '営業部', 'created_at' => $now, 'updated_at' => $now, 'is_deleted' => false],
            ['id' => 2, 'name' => '店舗運営部', 'created_at' => $now, 'updated_at' => $now, 'is_deleted' => false],
            ['id' => 3, 'name' => '経営企画本部', 'created_at' => $now, 'updated_at' => $now, 'is_deleted' => false],
            ['id' => 4, 'name' => '情報システム部', 'created_at' => $now, 'updated_at' => $now, 'is_deleted' => false],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->updateOrInsert(['id' => $dept['id']], $dept);
        }

        // 3. 店舗マスタ (stores)
        $stores = [
            [
                'id' => 1,
                'name' => '本部',
                'prefecture_id' => 13,
                'postal_code' => '100-0005',
                'address_detail' => '東京都千代田区丸の内1-1-1',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 2,
                'name' => '渋谷店',
                'prefecture_id' => 13,
                'postal_code' => '150-0002',
                'address_detail' => '東京都渋谷区渋谷2-21-1',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 3,
                'name' => '新宿店',
                'prefecture_id' => 13,
                'postal_code' => '160-0022',
                'address_detail' => '東京都新宿区新宿3-38-1',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 4,
                'name' => '梅田店',
                'prefecture_id' => 27,
                'postal_code' => '530-0001',
                'address_detail' => '大阪府大阪市北区梅田3-1-1',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
        ];

        foreach ($stores as $store) {
            DB::table('stores')->updateOrInsert(['id' => $store['id']], $store);
        }

        // 4. ユーザ (users) - id=0 は担当者未定・外部用の予約レコード
        $users = [
            [
                'id' => 0,
                'department_id' => null,
                'store_id' => null,
                'name' => '未定・外部担当者',
                'email' => 'unassigned@example.local',
                'password_hash' => bcrypt('unassigned'),
                'position' => null,
                'authority' => 'applicant',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 1,
                'department_id' => 1,
                'store_id' => 2,
                'name' => '山田 太郎',
                'email' => 'yamada@example.local',
                'password_hash' => bcrypt('password123'),
                'position' => '主任',
                'authority' => 'applicant',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 2,
                'department_id' => 2,
                'store_id' => 3,
                'name' => '鈴木 花子',
                'email' => 'suzuki@example.local',
                'password_hash' => bcrypt('password123'),
                'position' => '店舗スタッフ',
                'authority' => 'applicant',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 3,
                'department_id' => 3,
                'store_id' => 1,
                'name' => '佐藤 健一',
                'email' => 'sato@example.local',
                'password_hash' => bcrypt('password123'),
                'position' => '部長',
                'authority' => 'approver',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
            [
                'id' => 4,
                'department_id' => 1,
                'store_id' => 2,
                'name' => '高橋 誠',
                'email' => 'takahashi@example.local',
                'password_hash' => bcrypt('password123'),
                'position' => '渋谷店長',
                'authority' => 'approver',
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => false,
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(['id' => $user['id']], $user);
        }

        // 5. 初期サンプル稟議データ (まだデータが存在しない場合のみ投入)
        if (DB::table('approvals')->count() === 0) {
            // (1) 申請中 (status_id = 0)
            DB::table('approvals')->insert([
                'id' => 1,
                'applicant_user_id' => 1,
                'assignee_user_id' => 3,
                'assignee_name' => '佐藤 健一',
                'status_id' => 0,
                'subject' => '渋谷店 接客用タブレット端末（iPad 5台）導入の件',
                'amount' => 425000,
                'detail' => '渋谷店フロアでの在庫確認および電子カタログ案内用に、iPad（第10世代 64GB Wi-Fiモデル）5台および保護ケース一式を購入します。',
                'reason' => '週末の来店客増加に伴い、紙カタログでの案内待ち時間が発生しているため、接客効率化と顧客満足度向上を図るため。',
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
                'is_deleted' => false,
            ]);

            DB::table('approval_histories')->insert([
                'approval_id' => 1,
                'user_id' => 1,
                'operation_detail' => '稟議を新規申請しました',
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
                'is_deleted' => false,
            ]);

            DB::table('approval_files')->insert([
                'approval_id' => 1,
                'file_name' => '見積書_iPad5台一式_渋谷店.pdf',
                'file_url' => 'https://example.local/files/estimate_ipad_shibuya.pdf',
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
                'is_deleted' => false,
            ]);

            // (2) 差し戻し (status_id = 2)
            DB::table('approvals')->insert([
                'id' => 2,
                'applicant_user_id' => 1,
                'assignee_user_id' => 4,
                'assignee_name' => '高橋 誠',
                'status_id' => 2,
                'subject' => '夏季キャンペーン店頭POP・什器制作費用の申請',
                'amount' => 180000,
                'detail' => 'エントランス特設コーナー用の大型タペストリー2枚、アクリル什器10セットの制作発注。',
                'reason' => '7月からの季節商戦に向けた店頭視認性向上およびセット購入促進のため。',
                'created_at' => $now->copy()->subDays(4),
                'updated_at' => $now->copy()->subDays(1),
                'is_deleted' => false,
            ]);

            DB::table('approval_histories')->insert([
                [
                    'approval_id' => 2,
                    'user_id' => 1,
                    'operation_detail' => '稟議を新規申請しました',
                    'created_at' => $now->copy()->subDays(4),
                    'updated_at' => $now->copy()->subDays(4),
                    'is_deleted' => false,
                ],
                [
                    'approval_id' => 2,
                    'user_id' => 4,
                    'operation_detail' => '稟議を差し戻しました',
                    'created_at' => $now->copy()->subDays(1),
                    'updated_at' => $now->copy()->subDays(1),
                    'is_deleted' => false,
                ],
            ]);

            DB::table('approval_comments')->insert([
                'approval_id' => 2,
                'user_id' => 4,
                'content' => 'アクリル什器の単価が前回発注時より高くなっています。相見積もりの結果を詳細欄に追記の上、再申請してください。',
                'status' => 0,
                'created_at' => $now->copy()->subDays(1),
                'updated_at' => $now->copy()->subDays(1),
                'is_deleted' => false,
            ]);

            // (3) 承認済み (status_id = 1)
            DB::table('approvals')->insert([
                'id' => 3,
                'applicant_user_id' => 2,
                'assignee_user_id' => 3,
                'assignee_name' => '佐藤 健一',
                'status_id' => 1,
                'subject' => '新宿店 バックヤード業務用空調フィルター交換作業',
                'amount' => 95000,
                'detail' => '新宿店バックヤード空調機2台のフィルター清掃および劣化した部品の交換作業委託。',
                'reason' => '定期点検にて風量低下が指摘されており、夏場の室温上昇を防止するため。',
                'created_at' => $now->copy()->subDays(6),
                'updated_at' => $now->copy()->subDays(3),
                'is_deleted' => false,
            ]);

            DB::table('approval_histories')->insert([
                [
                    'approval_id' => 3,
                    'user_id' => 2,
                    'operation_detail' => '稟議を新規申請しました',
                    'created_at' => $now->copy()->subDays(6),
                    'updated_at' => $now->copy()->subDays(6),
                    'is_deleted' => false,
                ],
                [
                    'approval_id' => 3,
                    'user_id' => 3,
                    'operation_detail' => '稟議を承認しました',
                    'created_at' => $now->copy()->subDays(3),
                    'updated_at' => $now->copy()->subDays(3),
                    'is_deleted' => false,
                ],
            ]);

            DB::table('approval_comments')->insert([
                'approval_id' => 3,
                'user_id' => 3,
                'content' => '緊急性が高いため承認します。作業日程が確定したら店舗へ共有をお願いします。',
                'status' => 1,
                'created_at' => $now->copy()->subDays(3),
                'updated_at' => $now->copy()->subDays(3),
                'is_deleted' => false,
            ]);

            // (4) 下書き (status_id = 3)
            DB::table('approvals')->insert([
                'id' => 4,
                'applicant_user_id' => 1,
                'assignee_user_id' => 0,
                'assignee_name' => '未定（本部決裁枠）',
                'status_id' => 3,
                'subject' => '【下書き】店舗スタッフ向けインカム機器更新の件',
                'amount' => 320000,
                'detail' => '既存インカム10台のバッテリー劣化に伴う新型ワイヤレスインカムへの入れ替え（見積取得中）。',
                'reason' => '通話途切れによる業務ロス解消のため。',
                'created_at' => $now->copy()->subHours(5),
                'updated_at' => $now->copy()->subHours(5),
                'is_deleted' => false,
            ]);

            DB::table('approval_histories')->insert([
                'approval_id' => 4,
                'user_id' => 1,
                'operation_detail' => '下書きを作成しました',
                'created_at' => $now->copy()->subHours(5),
                'updated_at' => $now->copy()->subHours(5),
                'is_deleted' => false,
            ]);
        }

        // PostgreSQL のシーケンスを現在の最大IDに同期
        if (DB::getDriverName() === 'pgsql') {
            $tables = [
                'departments',
                'stores',
                'users',
                'approvals',
                'approval_histories',
                'approval_files',
                'approval_comments',
            ];
            foreach ($tables as $table) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), true)");
            }
        }
    }
}

