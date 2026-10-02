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
            $items = Request::param('items'); // [{ item_name, item_score, issue_image }]，兼容旧的 { item_id, issue_image }
            $baseUrl = trim((string) Request::param('base_url', ''));
            if (!$userId || !is_array($items) || empty($items)) {
                return api_json(['code' => 400, 'message' => '参数错误', 'data' => null]);
            }
            $user = User::find($userId);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            $checkDate = (string) Request::param('check_date') ?: date('Y-m-d');

            // 预取检查项目录：兼容旧前端按 item_id 提交，用于补齐名称与扣分值
            $itemIds = [];
            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                if ($itemId) {
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

            // 第一轮：解析名称/扣分值并做校验（任一不合法则整体不允许保存）
            $resolved = [];
            foreach ($items as $i => $item) {
                $seqNo = $i + 1;
                $issueImage = trim((string) ($item['issue_image'] ?? ''));
                if ($issueImage === '') {
                    return api_json(['code' => 400, 'message' => "第 {$seqNo} 张图片缺少问题图", 'data' => null]);
                }

                $itemId = (int) ($item['item_id'] ?? 0);
                $name = array_key_exists('item_name', $item) ? trim((string) $item['item_name']) : '';
                $scoreRaw = $item['item_score'] ?? null;
                if ($scoreRaw === null) {
                    $scoreRaw = '';
                }

                if ($itemId) {
                    if (!isset($itemMap[$itemId])) {
                        return api_json(['code' => 400, 'message' => "第 {$seqNo} 张图片选择的检查项不存在", 'data' => null]);
                    }
                    if ($name === '') {
                        $name = (string) $itemMap[$itemId]->name;
                    }
                    if (is_string($scoreRaw) && trim($scoreRaw) === '') {
                        $scoreRaw = $itemMap[$itemId]->score;
                    }
                }

                if ($name === '') {
                    return api_json(['code' => 400, 'message' => "第 {$seqNo} 张图片未填写检查项名称", 'data' => null]);
                }
                if (mb_strlen($name) > 64) {
                    return api_json(['code' => 400, 'message' => "第 {$seqNo} 张图片检查项名称不能超过 64 个字符", 'data' => null]);
                }
                // 扣分值必填且必须是数字（非负、有限）
                if (!is_numeric($scoreRaw)) {
                    return api_json(['code' => 400, 'message' => "第 {$seqNo} 张图片扣分值必须是数字", 'data' => null]);
                }
                $score = (float) $scoreRaw;
                if (!is_finite($score) || $score < 0) {
                    return api_json(['code' => 400, 'message' => "第 {$seqNo} 张图片扣分值必须是不小于 0 的数字", 'data' => null]);
                }
                $score = round($score, 2);

                $resolved[] = [
                    'item_id'     => $itemId ?: null,
                    'name'        => $name,
                    'score'       => $score,
                    'issue_image' => $issueImage,
                ];
            }

            // 第二轮：全部合法后才落库，序号从当天续号开始
            $startKey = $this->seq()->getNextSequenceKey($userId, $checkDate);
            $created = [];
            foreach ($resolved as $i => $row) {
                $record = Record::create([
                    'user_id'      => $userId,
                    'item_id'      => $row['item_id'],
                    'item_name_snapshot'  => $row['name'],
                    'item_score_snapshot' => $row['score'],
                    'sequence_key' => $startKey + $i,
                    'issue_image'  => $row['issue_image'],
                    'status'       => 'pending',
                    'check_date'   => $checkDate,
                ]);
                $created[] = Record::with(['item'])->find($record->id)->toArray();
            }

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
