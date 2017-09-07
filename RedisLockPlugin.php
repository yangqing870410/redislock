<?php
/**
 * redis并发锁插件（悲观锁）
 * User: qingyang
 * Date: 15/12/15
 * Time: 下午4:17
 */
class RedisLockPlugin {
    const SUCCESS = 0; // 成功
    const ERR_ADD_LOCK_UNIQUE = 1; // 锁存在
    const ERR_ADD_LOCK_TIME_DIFF = 2; // 锁操作时间错误
    const ERR_ADD_LOCK_EXCEPTION = 3; // 锁操作异常

    protected $opLimit = 3; //错误机制 尝试次数

    protected static $_redisServer = "";

    /*
     * 绑定对应的mysql数据库的表和redis组
     */
    public function __construct($redis) {
        $this->setRedisServer($redis);
    }

    /*
     * 获取redis服务信息
     */
    protected function setRedisServer($redis) {
        if (empty(self::$_redisServer)) {
            self::$_redisServer = $redis;
        }

        return true;
    }

    /**
     * 获取redis服务信息
     */
    protected function getRedisServer() {
        return self::$_redisServer;
    }

    /**
     * lock
     */
    public function lock($key, $timeout = 60)
    {
        try {
            $nowTime = time();
            static $opNum = 0;

            // 添加并发锁
            $redisSvr = $this->getRedisServer();
            $lockState = (int)$redisSvr->setnx($key, $nowTime);
            // 锁存在
            if ($lockState === 0) {
                // 获取锁信息
                $lockStartTime = $redisSvr->get($key);
                // 锁不存在
                if (empty($lockStartTime)) {
                    $opNum++;
                    if ($opNum > $this->opLimit) {
                        // 日志
                        error_log('加锁操作异常，超过操作失败次数上限,key:' . $key);
                        return self::ERR_ADD_LOCK_EXCEPTION;
                    }
                    $this->lock($key, $timeout);

                // 锁存在
                } else {
                    // 锁超时机制：判断t2 － t1 > locktime 锁过期
                    $lockEndTime = $nowTime;
                    $lockTime = $lockEndTime - $lockStartTime;
                    if ($lockTime > $timeout) {
                        // 锁超时
                        $newLockTime = $nowTime;
                        $oldLockTime = $redisSvr->getset($key, $newLockTime);
                        if ($lockStartTime != $oldLockTime) {
                            // 未获取锁
                            // 日志
                            error_log('加锁操作时间错误,key:'. $key);
                            return self::ERR_ADD_LOCK_TIME_DIFF;
                        }
                        return self::SUCCESS;
                    } else {
                        // 未获取锁
                        // 日志
                        error_log('加锁操作锁已存在,key:' . $key);
                        return self::ERR_ADD_LOCK_UNIQUE;
                    }
                }
            // 锁不存在 获取锁
            } else if ($lockState === 1) {
                return self::SUCCESS;
            }
        } catch (Exception $e) {
            // 日志
            error_log('加锁操作异常,key:' . $key . json_encode($e));
            return self::ERR_ADD_LOCK_EXCEPTION;
        }
    }

    /**
     * unlock
     */
    public function unlock($key)
    {
        $redisSvr = $this->getRedisServer();
        $result = $redisSvr->delete($key);
        if ($result === false) {
            // 日志
            error_log('删除锁操作异常,key:' . $key );
        }
        return $result;
    }
}