<?php
declare(strict_types=1);
namespace app\model;
use think\Model;
class Record extends Model
{
    protected $table = 'records';
    protected $schema = [
        'id'           => 'int',
        'user_id'      => 'int',
        'item_id'      => 'int',
        'item_name_snapshot'  => 'string',
        'item_score_snapshot' => 'float',
        'sequence_key' => 'int',
        'issue_image'  => 'string',
        'fix_image'    => 'string',
        'status'       => 'string',
        'check_date'   => 'date',
        'created_at'   => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function item()
    {
        return $this->belongsTo(InspectionItem::class, 'item_id', 'id');
    }
    // DECIMAL 列在部分驱动下会以字符串返回，统一转成数字，便于前端徽章直接展示
    public function getItemScoreSnapshotAttr($value)
    {
        return $value === null ? null : (float) $value;
    }
}
