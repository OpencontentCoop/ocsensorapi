<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Legacy\Utils;

trait BucketsTrait
{
    protected function getBuckets($field = 'published')
    {
        $data = [];

        $now = new \DateTime('now', Utils::getDateTimeZone());
        $start = (clone $now)->sub(new \DateInterval('PT8H'));
        $item = [
            'name' => '8h',
            'filter' => " $field range [{$start->format('c')},*] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P1D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P3D'))->setTime(23, 59);
        $item = [
            'name' => '1-3gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P3D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P7D'))->setTime(23, 59);
        $item = [
            'name' => '3-7gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P7D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P15D'))->setTime(23, 59);
        $item = [
            'name' => '7-15gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P15D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P30D'))->setTime(23, 59);
        $item = [
            'name' => '15-30gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P30D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P90D'))->setTime(23, 59);
        $item = [
            'name' => '30-90gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P90D'))->setTime(23, 59);
        $item = [
            'name' => 'oltre 90gg',
            'filter' => " $field range [*,{$end->format('c')}] and ",
        ];
        $data[] = $item;

        return $data;
    }
}