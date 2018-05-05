<?php

namespace Lib\Extend;

use Psr\Log\LoggerInterface;
use Lib\Extend\CrawlerDriver;
use Facebook\WebDriver\Cookie;
use League\Flysystem\Exception;
use Facebook\WebDriver\WebDriverBy;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Process\Process;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Exception\TimeOutException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Facebook\WebDriver\Exception\StaleElementReferenceException;

class Alipay extends CrawlerDriver
{
    protected $config = [];
    protected $cookiePath = 'alipay_cookies_selenium';

    public function __construct($config = array())
    {
        parent::__construct();
        //加载配置
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'account' => '',
            'password' => '',
            'to' => '',
        ));
        $this->config = $resolver->resolve($config);
        $this->cookiePath = $this->cookiePath.'_'.$this->config['account'];
    }

    /**
     * 第一步：登陆
     * 
     * @return [type] [description]
     */
    public function login()
    {
        //打开登陆页
        $this->get('https://auth.alipay.com/login/index.htm');
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::cssSelector('#J-loginMethod-tabs > li[data-status="show_login"]')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.login'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('登录失败，切换账号密码登陆失败');
            throw $e;
        }
        //切换到账号密码登陆
        $this->driver->findElement(
            WebDriverBy::cssSelector('#J-loginMethod-tabs > li[data-status="show_login"]')
        )->click();
        //输入账号密码，故意降低输入账号速度
        $input = $this->driver->findElement(WebDriverBy::id('J-input-user'));
        $words = str_split($this->config['account'], 1);
        foreach ($words as $word) {
            $input->sendKeys($word);
            usleep(150000);
        }
        $this->driver->findElement(WebDriverBy::id('password_rsainput'))
            ->sendKeys($this->config['password'])
            ->submit();
        //存在退出登录按钮表示登陆成功
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::id('J_logoutUrl')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.login'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('登录失败');
            throw $e;
        }
        $this->saveCookies();
    }

    /**
     * 第二步：进入账单查询页面
     * 
     * @return [type] [description]
     */
    public function entry($type = 'account')
    {
        try {
            $this->entryToRecord($type);
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.entry'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->removeCookies();
            $this->asyncMail('进入账单查询页面失败');
            throw $e;
        }
    }

    /**
     * 进入交易记录页面查询余额
     * 
     * @return [type] [description]
     */
    public function balance()
    {
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::cssSelector('.ft-green > strong')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.balance'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('查询余额失败');
            throw $e;
        }
        return $this->driver->findElement(
            WebDriverBy::cssSelector('.ft-green > strong')
        )->getText();
    }

    /**
     * 设置账单类型
     * 
     * @param string $category [description]
     */
    public function setAdvancedTradeType($category = 'TRANSFER')
    {
        try {
            $this->driver->wait(1800)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::id('tradeType')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.setAdvancedTradeType'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('设置账单类型失败');
            throw $e;
        }
        $this->driver->findElement(
            WebDriverBy::id('tradeType')
        )->click();
        $this->driver->findElement(
            WebDriverBy::cssSelector('.ui-select-item[data-value="'.$category.'"]')
        )->click();
    }

    /**
     * 抓取交易记录页面首页记录
     * @return [type] [description]
     */
    public function fetchRecordsByAdvanced()
    {
        try {
            $this->driver->wait(1800)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::id('J-set-query-form')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.fetchRecordsByAdvanced'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('抓取首页记录失败');
            throw $e;
        }
        $this->driver->findElement(
            WebDriverBy::id('J-set-query-form')
        )->click();

        $records = [];
        $trs = $this->driver->findElements(WebDriverBy::cssSelector('.J-item'));
        foreach ($trs as $tr) {
            $tds = $tr->findElements(WebDriverBy::cssSelector('td'));
            $record = [];
            foreach ($tds as $key => $td) {
                $record[$key] = $td->getText();
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * 抓取对账中心-收入-首页记录
     *
     * @return void
     */
    public function fetchByFundAccountDetail()
    {
        //点击“收入tab”
        $tabs = $this->checkFundAccountDetailByTabs();
        $tabs[1]->click();
        //等待ajax加载数据
        $this->waitForAjax($this->driver);
        //抓取首页交易记录
        return $this->getTableByFundAccountDetail();
    }

    /**
     * 在对账中心页面输入支付宝订单号查询
     *
     * @param [type] $tradeNo
     * @return void
     */
    public function queryByFundAccountDetail($tradeNo)
    {
        //点击最近30天
        $options = $this->checkFundAccountDetailByMoreQuery();
        $options[3]->click();
        //等待ajax加载数据
        $this->waitForAjax($this->driver);
        //点击更多查询条件
        $this->driver->findElement(WebDriverBy::cssSelector('.moreQueryTip___2CtRG'))->click();
        //输入支付宝订单号
        $this->driver->findElement(WebDriverBy::id('precisionQueryValue'))
            ->sendKeys($tradeNo);
        //点击搜索按钮
        $this->driver->findElement(WebDriverBy::cssSelector('.ant-btn.ant-btn-primary'))->click();
        //等待ajax加载
        $this->waitForAjax($this->driver);
        //抓取首页交易记录
        return $this->getTableByFundAccountDetail();
    }

    /**
     * 抓取指定日期段的第一页支出
     *
     * @param string $start 开始时间，如2018-04-01 00:00:00
     * @param string $end 结束时间，如2018-04-31 00:00:00
     * @return void
     */
    public function fetchFirstExpenseByFundAccountDetail($start, $end)
    {
        //点击最近30天
        $options = $this->checkFundAccountDetailByMoreQuery();
        //$options[3]->click();
        //等待ajax加载数据
        //$this->waitForAjax($this->driver);
        //设置起止时间
        $this->setTimeRange($start, $end);
        //点击更多查询条件
        $this->driver->findElement(WebDriverBy::cssSelector('.moreQueryTip___2CtRG'))->click();
        //点击账务类型后面的框框
        $this->driver->findElement(WebDriverBy::cssSelector('.ant-select-lg'))->click();
        //选择账务类型为交易
        $selectors = $this->driver->findElements(WebDriverBy::cssSelector('.ant-select-dropdown-menu-item'));
        if (count($selectors) != 9) {
            throw new \Exception('尝试更换账务类型为“交易”失败');
        }
        $selectors[1]->click();
        //点击搜索按钮
        $this->driver->findElement(WebDriverBy::cssSelector('.ant-btn.ant-btn-primary'))->click();
        //等待ajax加载数据
        $this->waitForAjax($this->driver);
        //点击“支出tab”
        $tabs = $this->checkFundAccountDetailByTabs();
        $tabs[2]->click();
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::elementTextIs(
                    WebDriverBy::cssSelector('.ant-tabs-tab-active'),
                    '支出'
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.fetchFirstExpenseByFundAccountDetail.ant-tabs-tab-active!=支出');
            throw $e;
        }
        //等待ajax加载数据
        $this->waitForAjax($this->driver);
        //抓取首页交易记录
        return $this->getTableByFundAccountDetail();
    }

    /**
     * 抓取下一页支出
     *
     * @return void
     */
    public function fetchNextExpenseByFundAccountDetail()
    {
        //点击下一页
        try {
            $nextBtn = $this->driver->findElement(WebDriverBy::cssSelector('.ant-pagination-next'));
            if (strstr($nextBtn->getAttribute('class'), 'ant-pagination-disabled') !== false) {
                throw new \Exception('已到最后一页');
            }
            $nextBtn->click();
        } catch (\Exception $e) {
            return false;
        }
        $this->waitForAjax($this->driver);
        return $this->getTableByFundAccountDetail();
    }

    /**
     * 抓取指定日期段的第一页交易退款
     *
     * @param string $start 开始时间，如2018-04-01 00:00:00
     * @param string $end 结束时间，如2018-04-31 00:00:00
     * @return void
     */
    public function fetchFirstRefundByFundAccountDetail($start, $end)
    {
        //点击最近30天
        $options = $this->checkFundAccountDetailByMoreQuery();
        //$options[3]->click();
        //等待ajax加载数据
        //$this->waitForAjax($this->driver);
        //设置起止时间
        $this->setTimeRange($start, $end);
        //点击更多查询条件
        $this->driver->findElement(WebDriverBy::cssSelector('.moreQueryTip___2CtRG'))->click();
        //点击账务类型后面的框框
        $this->driver->findElement(WebDriverBy::cssSelector('.ant-select-lg'))->click();
        //选择账务类型为交易退款
        $selectors = $this->driver->findElements(WebDriverBy::cssSelector('.ant-select-dropdown-menu-item'));
        if (count($selectors) != 9) {
            throw new \Exception('尝试更换账务类型为“交易退款”失败');
        }
        $selectors[2]->click();
        //点击搜索按钮
        $this->driver->findElement(WebDriverBy::cssSelector('.ant-btn.ant-btn-primary'))->click();
        //等待ajax加载数据
        $this->waitForAjax($this->driver);
        //点击“收入tab”
        $tabs = $this->checkFundAccountDetailByTabs();
        $tabs[1]->click();
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::elementTextIs(
                    WebDriverBy::cssSelector('.ant-tabs-tab-active'),
                    '收入'
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.fetchFirstExpenseByFundAccountDetail.ant-tabs-tab-active!=收入');
            throw $e;
        }
        //等待ajax加载数据
        $this->waitForAjax($this->driver);
        //抓取首页交易记录
        return $this->getTableByFundAccountDetail();
    }

    /**
     * 设置账务明细页面时间范围
     *
     * @return void
     */
    protected function setTimeRange($start, $end)
    {
        //点击日期输入框
        $this->driver->findElement(WebDriverBy::cssSelector('.dateRangeWrapper___3hCAb'))->click();
        //输入日期
        $input = $this->driver->findElement(WebDriverBy::cssSelector('input.ant-calendar-input'));
        $input->clear();
        $input->sendKeys($start);
        if ($input->getAttribute('value') != $start) {
            throw new \Exception('输入开始日期失败');
        }
        $input = $this->driver->findElement(WebDriverBy::cssSelector('div.ant-calendar-range-part.ant-calendar-range-right > div.ant-calendar-input-wrap > div.ant-calendar-date-input-wrap > input.ant-calendar-input'));
        $input->clear();
        $input->sendKeys($end);
        if ($input->getAttribute('value') != $end) {
            throw new \Exception('输入结束日期失败');
        }
        //确定日期
        $this->driver->findElement(WebDriverBy::cssSelector("a.ant-calendar-ok-btn"))->click();
        $this->waitForAjax($this->driver);
    }

    /**
     * 通过tabs检查是否已进入账务明细页面
     *
     * @return void
     */
    protected function checkFundAccountDetailByTabs()
    {
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::cssSelector('.ant-tabs-tab')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.fetchFundAccountDetail'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('进入对账中心页面失败');
            throw $e;
        }
        $tabs = $this->driver->findElements(WebDriverBy::cssSelector('.ant-tabs-tab'));
        if (count($tabs) != 3 || $tabs[1]->getText() != '收入') {
            throw new \Exception('未找到对账中心页面的收入按钮');
        }
        return $tabs;
    }
    /**
     * 通过查询按钮检查是否已进入账务明细页面
     *
     * @return void
     */
    protected function checkFundAccountDetailByMoreQuery()
    {
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::cssSelector('.moreQueryTip___2CtRG')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.queryByFundAccountDetail'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('查询支付宝订单号失败');
            throw $e;
        }
        $options = $this->driver->findElements(WebDriverBy::cssSelector('.quickTimeItem___r5kmW'));
        if (count($options) != 4) {
            throw new \Exception('未找到日期范围按钮');
        }
        return $options;
    }

    /**
     * 抓取账务明细页面的交易记录
     *
     * @return void
     */
    protected function getTableByFundAccountDetail()
    {
        $records = [];
        //尝试获取所有行次数
        $tryGetRows = 0;
        //获取所有行goto返回点
        goto_get_rows:
        //获取所有行
        $trs = $this->driver->findElements(WebDriverBy::cssSelector('.ant-spin-nested-loading .ant-table-scroll .ant-table-row.ant-table-row-level-0'));
        foreach ($trs as $row => $tr) {
            //尝试获取当前行所有列次数
            $tryGetCols = 0;
            //获取当前行所有列次数goto返回点
            goto_get_cols:
            //尝试获取所有列，如果失败，返回获取所有行
            try {
                $tds = $tr->findElements(WebDriverBy::cssSelector('td'));
            } catch (StaleElementReferenceException $e) {
                $this->log->error('getTableByFundAccountDetail.StaleElementReferenceException: '.$row.PHP_EOL.$e->getMessage());
                //返回去重新所有行
                if ($tryGetRows < 5) {
                    $tryGetRows ++;
                    goto goto_get_rows;
                }
                //超过尝试上限次数放弃
                throw $e;
            }
            $record = [];
            foreach ($tds as $col => $td) {
                try {
                    switch ($col) {
                        case 0:
                            $spans = $td->findElements(WebDriverBy::cssSelector('span'));
                            if (count($spans) != 5) {
                                throw new \Exception('时间字段格式不正确');
                            }
                            $record[$col] = $spans[1]->getText();
                            break;
                        case 1:
                            $span = $td->findElement(WebDriverBy::cssSelector('[data-clipboard-text]'));
                            $record[$col] = $span->getAttribute('data-clipboard-text');
                            break;
                        default:
                            $record[$col] = $td->getText();
                            break;
                    }
                } catch (StaleElementReferenceException $e) {
                    $this->log->error('getTableByFundAccountDetail.StaleElementReferenceException: '.$row.'_'.$col.PHP_EOL.$e->getMessage());
                    //返回去重新获取当前行所有列
                    if ($tryGetCols < 5) {
                        $tryGetCols ++;
                        goto goto_get_cols;
                    }
                    //超出尝试上限次数放弃
                    throw $e;
                }
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * 进入对账中心或者交易记录高级版
     *
     * @param string $type
     * @return void
     */
    protected function entryToRecord($type = 'account')
    {
        $url = $type == 'account' ? 'https://mbillexprod.alipay.com/enterprise/fundAccountDetail.htm' : 'https://consumeprod.alipay.com/record/advanced.htm';
        if (empty($this->history)) {
            $this->get('https://auth.alipay.com/login/index.htm?goto='.urlencode($url));
            $this->initCookies();
        }
        $this->get($url);
        try {
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(
                    WebDriverBy::id('J_logoutUrl')
                )
            );
        } catch (TimeOutException $e) {
            $this->log->error('Alipay.record'.PHP_EOL.$this->driver->getCurrentURL().PHP_EOL.$this->driver->getPageSource());
            $this->asyncMail('进入账单页面失败，登陆失效');
            throw $e;
        }
    }
}
