<?php
declare(strict_types=1);
namespace app\controller;
use app\model\InspectionItem;
use app\model\Record;
use app\model\User;
use app\service\QrService;
use app\service\RecordSequenceService;
use think\facade\Log;
use think\facade\Request;
use think\facade\Db;
use think\Response;
class RecordController
{
    protected function seq(): RecordSequenceService
    {
        return new RecordSequenceService();
    }

    private function normalizeCheckDate($checkDate): ?string
    {
        if (!$checkDate) {
            return null;
        }
        $checkDate = (string) $checkDate;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkDate)) {
            return '__INVALID__';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $checkDate);
        if (!$dt || $dt->format('Y-m-d') !== $checkDate) {
            return '__INVALID__';
        }
        return $checkDate;
    }

    public function index(): Response
    {
        try {
            $userId = Request::param('user_id');
            $token = Request::param('token');
            $checkDate = $this->normalizeCheckDate(Request::param('check_date'));
            $status = Request::param('status');
            if ($token) {
                $user = User::where('token', $token)->find();
                if (!$user) {
                    return api_json(['code' => 404, 'message' => '无效的 token', 'data' => null]);
                }
                if (isset($user->is_active) && (int) $user->is_active !== 1) {
                    return api_json(['code' => 403, 'message' => '账号已禁用', 'data' => null]);
                }
                $userId = $user->id;
            }
            if (!$userId) {
                return api_json(['code' => 400, 'message' => '缺少 user_id 或 token', 'data' => null]);
            }
            if ($checkDate === '__INVALID__') {
                return api_json(['code' => 400, 'message' => 'check_date 格式错误（应为 YYYY-MM-DD）', 'data' => null]);
            }
            $query = Record::with(['item'])->where('user_id', (int) $userId);
            if ($checkDate) {
                $query->where('check_date', $checkDate);
            }
            if ($status) {
                $query->where('status', $status);
            }
            $list = $query->order('sequence_key', 'asc')->select();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $list->toArray()]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error('RecordController@index: ' . $msg . "\n" . $e->getTraceAsString());
            if (stripos($msg, 'Unknown column') !== false && stripos($msg, 'check_date') !== false) {
                return api_json(['code' => 500, 'message' => '数据库缺少 records.check_date 字段，请执行 migrate_add_check_date.sql', 'data' => null]);
            }
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function save(): Response
    {
        try {
            $userId = (int) Request::param('user_id');
            $items = Request::param('items'); // [{ item_id?, item_name?, item_score?, issue_image }]
            $baseUrl = trim((string) Request::param('base_url', ''));
            if (!$userId || !is_array($items) || empty($items)) {
                return api_json(['code' => 400, 'message' => '参数错误', 'data' => null]);
            }
            $user = User::find($userId);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }

            // 先收集可能引用的检查项 ID（item_id 现在可为 0/空：允许管理员自由填写名称与扣分值）
            $itemIds = [];
            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                if ($itemId > 0) {
                    $itemIds[] = $itemId;
                }
            }
            $itemMap = [];
            if (!empty($itemIds)) {
                $rows = InspectionItem::whereIn('id', array_values(array_unique($itemIds)))->select();
                foreach ($rows as $row) {
                    $itemMap[(int) $row->id] = $row;
                }
            }

            // 逐条校验并解析出待写入数据：
            // - item_name 必填（为空但传了有效 item_id 时用预设名称兜底）
            // - item_score 必填且必须是 >=0 的数字，为空或非数字整批拒绝，不允许保存
            $parsed = [];
            foreach ($items as $index => $item) {
                $issueImage = trim((string) ($item['issue_image'] ?? ''));
                if ($issueImage === '') {
                    // 缺图片的条目直接跳过（兼容历史行为）
                    continue;
                }

                $itemId = (int) ($item['item_id'] ?? 0);
                if ($itemId > 0 && !isset($itemMap[$itemId])) {
                    return api_json(['code' => 400, 'message' => '第' . ($index + 1) . '张图片引用的检查项不存在', 'data' => null]);
                }
                $preset = $itemId > 0 ? $itemMap[$itemId] : null;

                $name = trim((string) ($item['item_name'] ?? ''));
                if ($name === '') {
                    $name = $preset ? (string) $preset->name : '';
                }
                if ($name === '') {
                    return api_json(['code' => 400, 'message' => '第' . ($index + 1) . '张图片未填写检查项名称', 'data' => null]);
                }

                $scoreRaw = $item['item_score'] ?? null;
                if ($scoreRaw === null || (is_string($scoreRaw) && trim($scoreRaw) === '')) {
                    $scoreRaw = $preset?->score;
                }
                if ($scoreRaw === null || (is_string($scoreRaw) && trim((string) $scoreRaw) === '')) {
                    return api_json(['code' => 400, 'message' => '第' . ($index + 1) . '张图片的扣分值不能为空', 'data' => null]);
                }
                if (!is_numeric($scoreRaw) || (float) $scoreRaw < 0) {
                    return api_json(['code' => 400, 'message' => '第' . ($index + 1) . '张图片的扣分值必须是不小于 0 的数字', 'data' => null]);
                }
                $score = (float) $scoreRaw;
                if ($score > 9999) {
                    return api_json(['code' => 400, 'message' => '第' . ($index + 1) . '张图片的扣分值过大', 'data' => null]);
                }

                $parsed[] = [
                    'item_id'     => $itemId > 0 ? $itemId : null,
                    'name'        => mb_substr($name, 0, 64),
                    'score'       => $score,
                    'issue_image' => $issueImage,
                ];
            }

            if (empty($parsed)) {
                return api_json(['code' => 400, 'message' => '缺少有效的图片数据', 'data' => null]);
            }

            $checkDate = (string) Request::param('check_date') ?: date('Y-m-d');
            $startKey = $this->seq()->getNextSequenceKey($userId, $checkDate);

            // 全部校验通过后再写入，使用事务保证不会出现半批脏数据
            $created = Db::transaction(function () use ($parsed, $userId, $checkDate, $startKey) {
                $created = [];
                foreach ($parsed as $i => $row) {
                    $record = Record::create([
                        'user_id'             => $userId,
                        'item_id'             => $row['item_id'],
                        'item_name_snapshot'  => $row['name'],
                        'item_score_snapshot' => $row['score'],
                        'sequence_key'        => $startKey + $i,
                        'issue_image'         => $row['issue_image'],
                        'status'              => 'pending',
                        'check_date'          => $checkDate,
                    ]);
                    $created[] = Record::with(['item'])->find($record->id)->toArray();
                }
                return $created;
            });

            // 可选：同一步生成“带 token 链接 + 唯一二维码”
            if ($baseUrl !== '') {
                $qr = (new QrService())->generateForUser($user, $baseUrl);
                return api_json([
                    'code' => 0,
                    'message' => 'ok',
                    'data' => [
                        'records' => $created,
                        'link' => $qr['link'],
                        'qr_code_url' => $qr['qr_code_url'],
                    ],
                ]);
            }

            return api_json(['code' => 0, 'message' => 'ok', 'data' => $created]);
        } catch (\Throwable $e) {
            Log::error('RecordController@save: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function delete(int $id): Response
    {
        try {
            $record = Record::find($id);
            if (!$record) {
                return api_json(['code' => 404, 'message' => '记录不存在', 'data' => null]);
            }
            $userId = $record->user_id;
            $seqKey = $record->sequence_key;
            $checkDate = $record->check_date ? (string) $record->check_date : null;
            $record->delete();
            $this->seq()->reorderAfterDelete($userId, $seqKey, $checkDate);
            return api_json(['code' => 0, 'message' => 'ok', 'data' => null]);
        } catch (\Throwable $e) {
            Log::error('RecordController@delete: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function uploadFix(int $id): Response
    {
        try {
            $record = Record::find($id);
            if (!$record) {
                return api_json(['code' => 404, 'message' => '记录不存在', 'data' => null]);
            }
            $token = (string) Request::param('token');
            if (!$token) {
                return api_json(['code' => 401, 'message' => '缺少 token', 'data' => null]);
            }
            $user = User::where('token', $token)->find();
            if (!$user) {
                return api_json(['code' => 401, 'message' => '无效的 token', 'data' => null]);
            }
            if ((int) $record->user_id !== (int) $user->id) {
                return api_json(['code' => 403, 'message' => '无权操作该记录', 'data' => null]);
            }
            $fixImage = Request::param('fix_image');
            if (!$fixImage) {
                return api_json(['code' => 400, 'message' => '缺少 fix_image', 'data' => null]);
            }
            $record->fix_image = $fixImage;
            $record->status = 'completed';
            $record->save();
            $record = Record::with(['item'])->find($record->id)->toArray();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $record]);
        } catch (\Throwable $e) {
            Log::error('RecordController@uploadFix: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
