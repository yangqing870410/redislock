# redis并发锁插件

## 使用说明
<pre><code>
// redis对象
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$plugin = new RedisLockPlugin($redis);
$key = 'test';
// 加锁
$plugin->lock($key);
// 解锁
$plugin->unlock($key);
</pre></code>
