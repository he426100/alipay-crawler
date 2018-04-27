# alipay-crawler

标签： crawler

---
1. 功能说明

- 无限循环抓取首页收款记录
- 指定任意时间段查询支出记录
- 支持多个支付宝账号

2. 使用方法

- 无限循环爬取账务明细首页收入记录（可用于实现个人收款）
```cmd
php cli.php alipay fetchHome
```

- 抓取指定任意时间段支出记录

```cmd
php cli.php alipay fetchAllExpense start="2018-04-01 00:00:00" end="2018-04-26 23:59:59"
```

3. 说明

- 本项目已实际投入使用，所以只公布核心代码
- selenium-ide和katalon都可以录制，但是katalon录制出来后无法设置时间段，所以我是先分别用两个插件录制，然后再用selenium-ide的部分步骤替换katalon，再用katalon导出php代码

4. 数据库
```sql
CREATE TABLE `xx_order` (
`id`  int(11) NOT NULL AUTO_INCREMENT ,
`alipay_account`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '支付宝账号' ,
`order_time`  int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '时间' ,
`memo`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`name`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`trade_no`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`other`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '对方' ,
`amount`  decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '金额' ,
`detail`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '明细' ,
`status`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '状态' ,
`used`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 未使用 1已使用' ,
`balance`  decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额' ,
`outer_order_sn`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '商家订单号' ,
`created_at`  datetime NOT NULL ,
`updated_at`  datetime NOT NULL ,
PRIMARY KEY (`id`),
UNIQUE INDEX `trade_no` (`trade_no`) USING BTREE 
)
ENGINE=InnoDB
```

5. 参考
    
- [https://github.com/facebook/php-webdriver](https://github.com/facebook/php-webdriver)
- [https://www.seleniumhq.org/projects/ide/](https://www.seleniumhq.org/projects/ide/)
- [katalon-recorder-selenium](https://chrome.google.com/webstore/detail/katalon-recorder-selenium/ljdobmomdgdljniojadhoplhkpialdid)
- [phpunit-formatter-for-kat](https://chrome.google.com/webstore/detail/phpunit-formatter-for-kat/gelokgfkbnkkcdbokielchgpfnphoalk)
- [selenium-ide](https://chrome.google.com/webstore/detail/selenium-ide/mooikfkahbdckldjjndioackbalphokd)
- [selenium ide 安装及脚本录制](https://blog.csdn.net/f1ngf1ngy1ng/article/details/79582771)
- [[自动化测试]Stale Element Reference Exception](https://www.jianshu.com/p/32e9442cf9c8)
- [Selenium菜鸟起步问题及解决办法记录](https://blog.csdn.net/freesigefei/article/details/50501961)
- [最好的语言PHP + 最好的前端测试框架Selenium = 最好的爬虫（上）](http://qsalg.com/?p=474)
- [用selenium+php-webdriver实现抓取淘宝页面](https://blog.minirplus.com/3829/)

