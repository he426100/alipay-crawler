<?php namespace App\Model;

class Order extends Model
{

    protected $table = 'order';
    
    public $timestamps = true;
    /**
     * 可以被批量赋值的属性。
     *
     * @var array
     */
    protected $fillable = ['order_time', 'memo', 'name', 'trade_no', 'other', 'amount', 'detail', 'status', 'balance', 'outer_order_sn', 'alipay_account'];
}
