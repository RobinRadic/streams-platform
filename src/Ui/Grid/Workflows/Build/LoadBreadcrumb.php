<?php

namespace Anomaly\Streams\Platform\Ui\Grid\Workflows\Build;

use Anomaly\Streams\Platform\Support\Breadcrumb;
use Anomaly\Streams\Platform\Ui\Grid\GridBuilder;

/**
 * Class LoadBreadcrumb
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class LoadBreadcrumb
{

    /**
     * Handle the command.
     *
     * @param GridBuilder $builder
     * @param Breadcrumb $breadcrumbs
     */
    public function handle(GridBuilder $builder, Breadcrumb $breadcrumbs)
    {
        if ($breadcrumb = $builder->grid->options->get('breadcrumb')) {
            $breadcrumbs->put($breadcrumb, '#');
        }
    }
}
