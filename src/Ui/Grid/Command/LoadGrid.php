<?php

namespace Anomaly\Streams\Platform\Ui\Grid\Command;

use Anomaly\Streams\Platform\Ui\Breadcrumb\BreadcrumbCollection;
use Anomaly\Streams\Platform\Ui\Grid\GridBuilder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Class LoadGrid
 *
 * @link   http://pyrocms.com/
 * @author PyroCMS, Inc. <support@pyrocms.com>
 * @author Ryan Thompson <ryan@pyrocms.com>
 */
class LoadGrid
{
    use DispatchesJobs;

    /**
     * The grid builder.
     *
     * @var GridBuilder
     */
    protected $builder;

    /**
     * Create a new LoadGrid instance.
     *
     * @param GridBuilder $builder
     */
    public function __construct(GridBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Handle the command.
     *
     * @param Container            $container
     * @param BreadcrumbCollection $breadcrumbs
     */
    public function handle(Container $container, BreadcrumbCollection $breadcrumbs)
    {
        $grid = $this->builder->getGrid();

        $grid->addData('grid', $grid);

        if ($handler = $grid->getOption('data')) {
            $container->call($handler, compact('grid'));
        }

        if ($breadcrumb = $grid->getOption('breadcrumb')) {
            $breadcrumbs->put($breadcrumb, '#');
        }
    }
}
