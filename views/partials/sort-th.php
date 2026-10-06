<?php
/**
 * Sortable table header cell: a link that sorts by the column, with an arrow showing the current state.
 *
 * @var string $label
 * @var string $column
 * @var string $sort current sort value, e.g. "name_asc"
 * @var string $baseUrl
 * @var array<string,mixed> $query current filters to keep in the link
 */
$direction = sortDirection($sort, $column);
$ariaSort = ['asc' => 'ascending', 'desc' => 'descending', '' => 'none'][$direction];
$arrow = ['asc' => 'arrow-up', 'desc' => 'arrow-down', '' => 'chevrons-up-down'][$direction];
?>
<th aria-sort="<?= $ariaSort ?>"><a class="th-sort<?= $direction !== '' ? ' is-sorted' : '' ?>" href="<?= e(sortUrl($baseUrl, $query, $column, $sort)) ?>"><?= e($label) ?><?= icon($arrow) ?></a></th>
