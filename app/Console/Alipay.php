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
        if (isset($argv['port'])) {
            $settings['port'] = $argv['port'];
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
     * 爬取首页转账记录
     * 
     * @return [type] [description]
     */
    public function fetchHome($name = '转账', $tab = '收入', $acceptNames = '转账,收款')
    {
        if ($name == 'all') {
            $name = '全部';
        }
        $tab = $this->getTabByName($name, $tab);
        $acceptNames = $this->getAcceptNamesByName($name, $acceptNames);
    
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
                $records = $this->driver->fetchByFundAccountDetail($tab);//fetchFundAccountDetail
                $this->saveRecords($records, $acceptNames);
                $this->cache->set('alipay_fetch_last_time_'.$this->settings['account'], time(), 3600);
            } catch (\Exception $e) {
                throw $e;
            }

            sleep(10);
        }
    }

    /**
     * 查询指定支付宝订单号
     *
     * @param string $tradeNo
     * @return void
     */
    public function query($tradeNo, $acceptNames = ['转账', '收款', '交易'])
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
                if (($_record = $this->formatAccount($record, $acceptNames)) !== false) {
                    $_records[] = $_record;
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
        return json_encode($_records);
    }

    /**
     * 爬取指定时间段的交易记录
     * 
     * @param string $name 账务类型
     * @param string $start 开始时间
     * @param string $end 结束时间
     * @param string $tab
     * @param string $acceptNames
     * @return [type] [description]
     */
    public function fetchAll($name, $start, $end, $tab = '', $acceptNames = '')
    {
        if ($name == 'all') {
            $name = '全部';
        }
        $tab = $this->getTabByName($name, $tab);
        $acceptNames = $this->getAcceptNamesByName($name, $acceptNames);
        $this->entry();
        try {
            $records = $this->driver->fetchFirstByFundAccountDetail($name, $tab, $start, $end);
            $this->saveRecords($records, $acceptNames);
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
                $this->saveRecords($records, $acceptNames);

                sleep(5);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 通过账务类型获取tab
     *
     * @param string $name 账务类型
     * @param string $tab
     * @return string
     */
    protected function getTabByName($name, $tab = '')
    {
        if (empty($tab)) {
            if ($name == '交易') {
                $tab = '支出';
            } elseif ($name == '交易退款') {
                $tab = '收入';
            } else {
                $tab = '全部';
            }
        }
        if ($tab == 'all') {
            $tab = '全部';
        }
        return $tab;
    }

    /**
     * 通过账务类型获取可接受账务类型
     *
     * @param string $name 账务类型
     * @param string $acceptNames
     * @return array
     */
    protected function getAcceptNamesByName($name, $acceptNames = '')
    {
        if (empty($acceptNames)) {
            $acceptNames = [];
            if ($name != '全部') {
                $acceptNames[] = $name;
            }
            if ($name == '转账') {
                $acceptNames[] = '收款';
            }
        } else {
            $acceptNames = $acceptNames == '全部' || $acceptNames == 'all' ? [] : explode(',', $acceptNames);
        }
        return $acceptNames;
    }

    /**
     * 保存一批记录
     *
     * @param [type] $records
     * @return void
     */
    protected function saveRecords($records, $acceptNames = [])
    {
        if (empty($records)) {
            return false;
        }
        try {
            foreach ($records as $record) {
                $data = $this->formatAccount($record, $acceptNames);
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
    protected function formatAccount($row, $acceptNames = [])
    {
        if (count($row) != 8) {
            //throw new \Exception('行数据格式不正确：'.PHP_EOL.var_export($row, true));
            $this->logger->error('formatAccount.行数据格式不正确：'.PHP_EOL.var_export($row, true));
            return false;
        }
        list($time, $tradeNo, $outerOrderSn, $other, $name, $amount, $balance) = $row;
        //检查账务类型
        if (!empty($acceptNames) && !in_array($name, $acceptNames)) {
            $this->logger->error('formatAccount.name'.PHP_EOL.var_export($acceptNames, true).PHP_EOL.var_export($row, true));
            return false;
        }
        //检查支付宝订单号
        if (!preg_match('/^\d{16,}$/', $tradeNo)) {
            $this->logger->error('formatAccount.tradeNo'.PHP_EOL.var_export($row, true));
            return false;
        }
        if ($this->cache->has('order_'.$tradeNo.'_'.$name)) {
            return false;
        }
        //金额转数字（去掉符号）
        $amount = floatval($amount);
        //其他
        $other = str_replace("\n", ' ', str_replace('"', '', $other));
        $time = str_replace("\n", ' ', str_replace('"', '', $time));
        $time = strtotime($time);
        $balance = str_replace(',', '', $balance);

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
        //金额转数字（去掉符号）
        $amount = floatval($amount);
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
