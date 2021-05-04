<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace tests\drivers;

use tests\app\HeavyJob;
use Yii;
use tests\app\SimpleJob;
use yii\queue\Queue;

/**
 * Driver Test Case.
 *
 * @author Roman Zhuravlev <zhuravljov@gmail.com>
 */
abstract class TestCase extends \tests\TestCase
{
    /**
     * @return Queue
     */
    abstract protected function getQueue();

    /**
     * @return SimpleJob
     */
    protected function createSimpleJob()
    {
        return new SimpleJob([
            'uid' => uniqid(),
        ]);
    }

    /**
     * @param int $load
     * @return HeavyJob
     */
    protected function createHeavyJob($load=0)
    {
        return new HeavyJob([
            'uid'   => uniqid(),
            'load'  =>$load
        ]);
    }

    /**
     * @param SimpleJob $job
     */
    protected function assertSimpleJobDone(SimpleJob $job)
    {
        $timeout = 5000000; // 5 sec
        $step = 50000;
        while (!file_exists($job->getFileName()) && $timeout > 0) {
            usleep($step);
            $timeout -= $step;
        }
        $this->assertFileExists($job->getFileName());
    }

    /**
     * @param SimpleJob $job
     * @param int $delay
     */
    protected function assertSimpleJobLaterDone(SimpleJob $job, $delay)
    {
        $time = time() + $delay;
        sleep($delay);
        $timeout = 5000000; // 5 sec
        $step = 50000;
        while (!file_exists($job->getFileName()) && $timeout > 0) {
            usleep($step);
            $timeout -= $step;
        }
        $this->assertFileExists($job->getFileName());
        $this->assertGreaterThanOrEqual($time, filemtime($job->getFileName()));
    }

    /**
     * @param HeavyJob $job
     * @param int $delay
     * @param $initialTime
     */
    protected function assertHeavyJobLaterDone(HeavyJob $job, $delay, $initialTime)
    {
        $time = $initialTime + $delay;
        sleep($delay);
        $timeout = 5000000; // 5 sec
        $step = 50000;
        while (!file_exists($job->getFileName()) && $timeout > 0) {
            usleep($step);
            $timeout -= $step;
        }
        $this->assertFileExists($job->getFileName());
        $this->assertGreaterThanOrEqual($time, filemtime($job->getFileName()));
    }

    /**
     * @param HeavyJob $job
     * @param int $delay
     */
    protected function assertHeavyJobDelayedFifoDone(HeavyJob $job1, HeavyJob $job2, $msg)
    {
        $this->assertFileExists($job1->getFileName());
        $this->assertFileExists($job2->getFileName());
        $this->assertGreaterThanOrEqual(filemtime($job1->getFileName()), filemtime($job2->getFileName()), $msg);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        // Removes temp job files
        foreach (glob(Yii::getAlias("@runtime/job-*.lock")) as $fileName) {
            unlink($fileName);
        }

        parent::tearDown();
    }
}
