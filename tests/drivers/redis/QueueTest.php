<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace tests\drivers\redis;

use tests\app\RetryJob;
use tests\drivers\CliTestCase;
use Yii;
use yii\queue\redis\Queue;

/**
 * Redis Queue Test.
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
class QueueTest extends CliTestCase
{
    public function testRun()
    {
        $job = $this->createSimpleJob();
        $this->getQueue()->push($job);
        $this->runProcess(['php', 'yii', 'queue/run']);

        $this->assertSimpleJobDone($job);
    }

    public function testStatus()
    {
        $job = $this->createSimpleJob();
        $id = $this->getQueue()->push($job);
        $isWaiting = $this->getQueue()->isWaiting($id);
        $this->runProcess(['php', 'yii', 'queue/run']);
        $isDone = $this->getQueue()->isDone($id);

        $this->assertTrue($isWaiting);
        $this->assertTrue($isDone);
    }

    public function testListen()
    {
        $this->startProcess(['php', 'yii', 'queue/listen', '1']);
        $job = $this->createSimpleJob();
        $this->getQueue()->push($job);

        $this->assertSimpleJobDone($job);
    }

    public function testLater()
    {
        $this->startProcess(['php', 'yii', 'queue/listen', '1']);
        $job = $this->createSimpleJob();
        $this->getQueue()->delay(2)->push($job);

        $this->assertSimpleJobLaterDone($job, 2);
    }

    public function testHeavyJobDelayedFifo()
    {
        $this->startProcess(['php', 'yii', 'queue/listen', '1']);

        $delay = 1;
        $load = $delay+1;
        $initialTimeFirstBatch = time();

        $job1 = $this->createHeavyJob($load);
        $this->getQueue()->delay($delay)->push($job1);

        $job2 = $this->createHeavyJob($load);
        $this->getQueue()->delay($delay)->push($job2);

        sleep($delay);

        $initialTimeSecondBatch = time();
        $job3 = $this->createHeavyJob($load);
        $this->getQueue()->delay($delay)->push($job3);


        $this->assertHeavyJobLaterDone($job1, $delay, $initialTimeFirstBatch);
        $this->assertHeavyJobLaterDone($job2, $delay, $initialTimeFirstBatch);
        $this->assertHeavyJobLaterDone($job3, $delay, $initialTimeSecondBatch);

        $this->assertHeavyJobDelayedFifoDone($job1, $job2, 'Job1 < Job2');
        $this->assertHeavyJobDelayedFifoDone($job2, $job3, 'Job2 < Job3');
    }

    public function testRetry()
    {
        $this->startProcess(['php', 'yii', 'queue/listen', '1']);
        $job = new RetryJob(['uid' => uniqid()]);
        $this->getQueue()->push($job);
        sleep(6);

        $this->assertFileExists($job->getFileName());
        $this->assertEquals('aa', file_get_contents($job->getFileName()));
    }

    public function testClear()
    {
        $this->getQueue()->push($this->createSimpleJob());
        $this->assertNotEmpty($this->getQueue()->redis->keys($this->getQueue()->channel . '.*'));
        $this->runProcess(['php', 'yii', 'queue/clear', '--interactive=0']);

        $this->assertEmpty($this->getQueue()->redis->keys($this->getQueue()->channel . '.*'));
    }

    public function testRemove()
    {
        $id = $this->getQueue()->push($this->createSimpleJob());
        $this->assertTrue((bool) $this->getQueue()->redis->hexists($this->getQueue()->channel . '.messages', $id));
        $this->runProcess(['php', 'yii', 'queue/remove', $id]);

        $this->assertFalse((bool) $this->getQueue()->redis->hexists($this->getQueue()->channel . '.messages', $id));
    }

    /**
     * @return Queue
     */
    protected function getQueue()
    {
        return Yii::$app->redisQueue;
    }

    protected function tearDown()
    {
        $this->getQueue()->redis->flushdb();
        parent::tearDown();
    }
}
