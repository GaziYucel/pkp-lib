<?php

/**
 * @file controllers/grid/admin/context/ContextGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextGridCellProvider
 * @ingroup controllers_grid_admin_context
 *
 * @brief Subclass for a context grid column's cell provider
 */

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;

class ContextGridCellProvider extends GridCellProvider
{
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert(is_a($element, 'Context') && !empty($columnId));
        switch ($columnId) {
            case 'name':
                $label = $element->getLocalizedName() != '' ? $element->getLocalizedName() : __('common.untitled');
                return ['label' => $label];
                break;
            case 'urlPath':
                $label = $element->getPath();
                return ['label' => $label];
                break;
            default:
                break;
        }
    }
}
