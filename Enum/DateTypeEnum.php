<?php

namespace Leantime\Plugins\ProjectOverview\Enum;

enum DateTypeEnum: string
{
    case THIS_WEEK = 'this week';
    case NEXT_TWO_WEEKS = 'next two weeks';
    case NEXT_THREE_WEEKS = 'next three weeks';
    case CUSTOM = 'custom';
}
