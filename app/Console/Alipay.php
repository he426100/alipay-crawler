<?php

namespace App\Console;

use App\Model\AlipayOrder;
use League\Plates\Engine;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Lib\Extend\Alipay as AlipayDriver;
use Illuminate\Database\ConnectionInterface;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Exception\NoSuchElementException;

class Alipay extends Command
{
    public function __construct(Engine $view, LoggerInterface $logger)
    {
        parent::__construct($view, $logger);

        $app = app();
        $argv = $app->getConfig('argv');
        if (isset($argv['alipay_account']) && isset($argv['alipay_password'])) {
            $settings = [
                'account' => $argv['alipay_account'],
                'password' => $argv['alipay_password'],
            ];
            if (isset($argv['alipay_to'])) {
                $settings['to'] = $argv['alipay_to'];
            }
        } else {
            $settings = $app->getConfig('settings.alipay');
        }
        $this->settings = $settings;
        $this->driver = new AlipayDriver($settings);
        $this->cache = $app->resolve(CacheInterface::class);
        $this->db = $app->resolve(ConnectionInterface::class);
    }
    /**
     * 登陆
     * 
     * @return [type] [description]
     */
    public function login()
    {
        $this->driver->login();
    }

    /**
     * 查询余额
     * 
     * @return [type] [description]
     */
    public function balance()
    {
        $this->driver->entry('advanced');
        return $this->driver->balance();
    }

    /**
     * 爬取首页转账（收入）记录
     * 
     * @return [type] [description]
     */
    public function fetchHome()
    {
        $this->entry();
        while (true) {
            $hour = intval(date('H'));
            if ($hour < 1 && !$this->cache->has('alipay_fetch_refreshed_'.$this->settings['account'])) {
                try {
                    $this->driver->refresh();
                    $this->cache->set('alipay_fetch_refreshed_'.$this->settings['account'], time(), 7200);
                } catch (\Exception $e) {
                    throw $e;
                }
            }
            try {
                $records = $this->driver->fetchByFundAccountDetail();//fetchFundAccountDetail
                $this->saveRecords($records);
                $this->cache->set('alipay_fetch_last_time_'.$this->settings['account'], time(), 3600);
            } catch (\Exception $e) {
                throw $e;
            }

            sleep(15);
        }
    }

    /**
     * 查询指定支付宝订单号
     *
     * @param string $tradeNo
     * @return void
     */
    public function query($tradeNo)
    {
        $this->entry();
        try {
            $records = $this->driver->queryByFundAccountDetail($tradeNo);
        } catch (\Exception $e) {
            throw $e;
        }
        $_records = [];
        try {
            foreach ($records as $record) {
                $_records[] = $this->formatAccount($record);
            }
        } catch (\Exception $e) {
            throw $e;
        }
        return json_encode($_records);
    }

    /**
     * 爬取指定时间段的交易（支出）记录
     * 
     * @return [type] [description]
     */
    public function fetchAllExpense($start, $end)
    {
        $this->entry();
        try {
            $records = $this->driver->fetchFirstExpenseByFundAccountDetail($start, $end);
            $this->saveRecords($records, ['交易'], '-', true);
            while (true) {
                try {
                    $records = $this->driver->fetchNextExpenseByFundAccountDetail();
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    continue;
                }
                if ($records === false || empty($records)) {
                    break;
                }
                $this->saveRecords($records, ['交易'], '-', true);

                sleep(5);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 爬取指定时间段的交易退款记录
     * 
     * @return [type] [description]
     */
    public function fetchAllRefund($start, $end)
    {
        $this->entry();
        try {
            $records = $this->driver->fetchFirstRefundByFundAccountDetail($start, $end);
            $this->saveRecords($records, ['交易退款']);
            while (true) {
                try {
                    $records = $this->driver->fetchNextExpenseByFundAccountDetail();
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    continue;
                }
                if ($records === false || empty($records)) {
                    break;
                }
                $this->saveRecords($records, ['交易退款']);

                sleep(5);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 保存一批记录
     *
     * @param [type] $records
     * @return void
     */
    protected function saveRecords($records, $acceptNames = ['转账', '收款'], $symbol = '+', $canNegative = false)
    {
        if (empty($records)) {
            return false;
        }
        try {
            foreach ($records as $record) {
                $data = $this->formatAccount($record, $acceptNames, $symbol, $canNegative);
                if (is_array($data)) {
                    $this->save($data);
                }
            }
            return true;
        } catch (\Exception $e) {
            throw $e;
        }
        return false;
    }

    /**
     * 格式化账务明细页面的一行交易记录
     *
     * @param array $row 一行数据
     * @param array $acceptNames 可接受的账务类型
     * @return void
     */
    protected function formatAccount($row, $acceptNames = ['转账', '收款'], $symbol = '+', $canNegative = false)
    {
        if (count($row) != 8) {
            //throw new \Exception('行数据格式不正确：'.PHP_EOL.var_export($row, true));
            $this->logger->error('formatAccount.行数据格式不正确：'.PHP_EOL.var_export($row, true));
            return false;
        }
        list($time, $tradeNo, $outerOrderSn, $other, $name, $amount, $balance) = $row;
        //检查账务类型
        if (!in_array($name, $acceptNames)) {
            //throw new \Exception('当前行非转账类型');
            $this->logger->error('formatAccount.name'.PHP_EOL.var_export($row, true));
            return false;
        }
        //检查支付宝订单号
        if (!preg_match('/^\d{28}$/', $tradeNo) && !preg_match('/^\d{32}$/', $tradeNo)) {
            //throw new \Exception('当前行非转账类型');
            $this->logger->error('formatAccount.tradeNo'.PHP_EOL.var_export($row, true));
            return false;
        }
        if ($this->cache->has('order_'.$tradeNo.'_'.$name)) {
            return false;
        }
        //检查金额，比如不可为负数
        if (substr($amount, 0, 1) != $symbol) {
            return false;
        }
        $amount = floatval(str_replace($symbol, '', $amount));
        if (!$canNegative && $amount <= 0) {
            return false;
        }
        if ($symbol == '-') {
            $amount = bcmul($amount, -1, 2); 
        }
        //其他
        $other = str_replace("\n", ' ', str_replace('"', '', $other));
        $time = str_replace("\n", ' ', str_replace('"', '', $time));
        $time = strtotime($time);

        return [
            'order_time' => $time,
            'trade_no' => $tradeNo,
            'outer_order_sn' => $outerOrderSn,
            'other' => $other,
            'name' => $name,
            'amount' => $amount,
            'balance' => $balance,
        ];
    }

    /**
     * 格式化交易记录页面的一行交易记录
     *
     * @param array $row
     * @return void
     */
    protected function formatAdvanced($row)
    {
        if (count($row) != 9) {
            throw new \Exception('行数据格式不正确');
        }
        //
        list($time, $memo, $name, $tradeNo, $other, $amount, $detail, $status, $action) = $row;
        if ($name != '转账' && $name != '收款') {
            //throw new \Exception('当前行非转账类型');
            $this->logger->error('format.name'.PHP_EOL.var_export($row, true));
            return false;
        }
        $tradeNo = str_replace('流水号:', '', $tradeNo);
        if (!preg_match('/^\d{32}$/', $tradeNo)) {
            //throw new \Exception('当前行非转账类型');
            $this->logger->error('format.tradeNo'.PHP_EOL.var_export($row, true));
            return false;
        }
        if ($this->cache->has('order_'.$tradeNo.'_'.$name)) {
            return false;
        }
        if ($status != '交易成功') {
            return false;
        }
        //格式化数据
        if (substr($amount, 0, 2) != '+ ') {
            return false;
        }
        $amount = floatval(str_replace('+ ', '', $amount));
        if ($amount <= 0) {
            return false;
        }
        $time = str_replace('"', '', $time);
        $time = str_replace("\n", ' ', $time);
        $time = str_replace('.', '-', $time);
        $time = strtotime($time);

        return [
            'order_time' => $time,
            'memo' => $memo,
            'name' => $name,
            'trade_no' => $tradeNo,
            'other' => $other,
            'amount' => $amount,
            'detail' => $detail,
            'status' => $status,
        ];
    }

    /**
     * 保存一行交易记录
     * 
     * @param  array $data 交易记录
     * @return [type]      [description]
     */
    protected function save($data)
    {
        if ($this->db->table('alipay_order')->where('trade_no', $data['trade_no'])->where('name', $data['name'])->selectRaw('count(*) number')->value('number') > 0) {
            $this->cache->set('order_'.$data['trade_no'].'_'.$data['name'], time(), 3600);
            return false;
        }
        $data['alipay_account'] = $this->settings['account'];
        $this->logger->info(var_export($data, true));
        return AlipayOrder::create($data);
    }

    /**
     * 登陆支付宝并进入账务明细页面（反复尝试）
     *
     * @return void
     */
    protected function entry()
    {
        $entered = false;
        $checked = $this->driver->checkLogin();
        while (!$checked || !$entered) {
            if (!$checked) {
                $this->driver->login();
            }
            try {
                $this->driver->entry('account');
                $entered = true;
            } catch (TimeOutException $e) {
            }
            $checked = $this->driver->checkLogin();
        }
    }
}
