<?php

namespace Anomaly\Streams\Platform\Ui\Table\Workflows;

use Anomaly\Streams\Platform\Workflow\Workflow;
use Anomaly\Streams\Platform\Ui\Table\Workflows\Views\GuessViews;
use Anomaly\Streams\Platform\Ui\Support\Workflows\BuildComponents;
use Anomaly\Streams\Platform\Ui\Support\Workflows\MergeComponents;
use Anomaly\Streams\Platform\Ui\Support\Workflows\ParseComponents;
use Anomaly\Streams\Platform\Ui\Table\Workflows\Views\DefaultViews;
use Anomaly\Streams\Platform\Ui\Support\Workflows\ResolveComponents;
use Anomaly\Streams\Platform\Ui\Table\Workflows\Views\NormalizeViews;
use Anomaly\Streams\Platform\Ui\Support\Workflows\TranslateComponents;

/**
 * Class ViewsWorkflow
 *
 * @link    http://pyrocms.com/
 * @author  PyroCMS, Inc. <support@pyrocms.com>
 * @author  Ryan Thompson <ryan@pyrocms.com>
 */
class ViewsWorkflow extends Workflow
{

    /**
     * The build steps.
     *
     * @var array
     */
    protected $steps = [
        'resolve_views' => ResolveComponents::class,

        DefaultViews::class,
        NormalizeViews::class,

        'merge_views' => MergeComponents::class,
        
        'translate_views' => TranslateComponents::class,
        'parse_views' => ParseComponents::class,

        'build_views' => BuildComponents::class,
    ];
}
