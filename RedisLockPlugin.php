<?php
/**
 * redis并发锁插件（悲观锁）
 * User: qingyang
 * Date: 15/12/15
 * Time: 下午4:17
 */
class RedisLockPlugin {
    /**
     * redis对象
     * @var string
     */
    protected static $_redisServer = "";

    /**
     * 锁等待时间，防止饥饿进程
     * @var int
     */
    protected static $timeoutTime = 1000000; // 单位微秒 默认1秒

    /**
     * 休眠时间，防止冲突碰撞
     * @var int
     */
    protected static $sleepTime = 100000; // 单位微秒 默认100毫秒

    /**
     * 锁超时时间，防止入锁以后，无限的执行等待 单位微秒 默认60秒
     * @var int
     */
    protected static $expireTime = 60000000;

    /*
     * 绑定对应的mysql数据库的表和redis组
     */
    public function __construct($redis) {
        self::$_redisServer = $redis;
    }

    /**
     * lock
     * @param $key string redis的key
     * @param string $expireTime int 锁的有效期 单位微妙
     * @return bool true表示成功 false表示失败
     */
    public function lock($key, $expireTime = '')
    {
        try {
            // 初始化变量
            if (empty($expireTime)) {
                $expireTime = self::$expireTime;
            }
            $timeout = self::$timeoutTime;
            $redisSvr = self::$_redisServer;
            $sleep = self::$sleepTime;

            while ($timeout > 0) {
                $nowTime = floatval(microtime(true));
                $expires = $nowTime + $expireTime / 1000 / 1000;

                // 添加并发锁
                $lockState = (int)$redisSvr->setnx($key, $expires);
                // 锁不存在 获取到锁
                if ($lockState) {
                    return true;
                // 锁存在 未获取到锁
                } else {
                    // 获取锁信息 锁的时间戳
                    $lockTime = floatval($redisSvr->get($key));
                    // 锁存在并且锁超时超时
                    if (!empty($lockTime) && $lockTime < $nowTime) {
                        // 锁超时
                        $oldLockTime = $redisSvr->getset($key, $expires);
                        // 获取上一个锁到期时间，并设置现在的锁到期时间
                        if (!empty($oldLockTime) && bccomp($lockTime, $oldLockTime) === 0) {
                            return true;
                        }
                    }
                    echo '锁时间:' . $lockTime . ',当前时间:' . $nowTime . PHP_EOL;
                    echo '剩余时间:' . ($lockTime - $nowTime) . PHP_EOL;
                }

                $timeout -= $sleep;
                echo '休眠时间:' . $sleep . '，超时时间:' . $timeout . PHP_EOL;
                usleep($sleep);
            }

            return false;
        } catch (Exception $e) {
            error_log('加锁操作异常,key:' . $key . json_encode($e));
            return false;
        }
    }

    /**
     * unlock
     * @param $key string redis的key
     * @return bool true表示成功 false表示失败
     */
    public function unlock($key)
    {
        try {
            $redisSvr = self::$_redisServer;

            $result = $redisSvr->delete($key);
            if ($result === false) {
                error_log('删除锁操作异常,key:' . $key );
            }
            return $result;
        } catch (Exception $e) {
            error_log('加锁操作异常,key:' . $key . json_encode($e));
            return false;
        }
    }
}