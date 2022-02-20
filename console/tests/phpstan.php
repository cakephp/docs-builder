<?php
declare(strict_types=1);

// phpcs:disable

trait ModelAwareTrait {
}
trait LocatorAwareTrait {
}

class_alias(\ModelAwareTrait ::class, \Cake\Datasource\ModelAwareTrait ::class, false);
class_alias(\LocatorAwareTrait::class, \Cake\ORM\Locator\LocatorAwareTrait::class, false);

// phpcs:enable
