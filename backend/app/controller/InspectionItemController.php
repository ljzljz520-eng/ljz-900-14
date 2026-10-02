<?php
declare(strict_types=1);
namespace app\controller;
use app\model\InspectionItem;
use think\facade\Log;
use think\facade\Request;
use think\Response;
class InspectionItemController
{
    public function index(): Response
    {
        try {
            $list = InspectionItem::select();
            $data = $list->isEmpty() ? [] : $list->toArray();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('InspectionItemController@index: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    /**
     * 管理员上传时新建检查项：名称必填；扣分值为空或不是数字时不允许保存
     */
    public function create(): Response
    {
        try {
            $name = trim((string) Request::param('name', ''));
            if ($name === '' || mb_strlen($name) > 64) {
                return api_json(['code' => 400, 'message' => '检查项名称不能为空（1~64 字）', 'data' => null]);
            }
            $scoreRaw = Request::param('score');
            if ($scoreRaw === null || (is_string($scoreRaw) && trim($scoreRaw) === '') || !is_numeric($scoreRaw)) {
                return api_json(['code' => 400, 'message' => '扣分值不能为空且必须是数字', 'data' => null]);
            }
            $score = (int) round((float) $scoreRaw);
            $item = InspectionItem::create([
                'name'  => $name,
                'score' => $score,
            ]);
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $item->toArray()]);
        } catch (\Throwable $e) {
            Log::error('InspectionItemController@create: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
