<?php

use yii\db\Migration;

/**
 * Handles adding order_number to table `logs`.
 */
class m170821_131906_add_order_number_column_to_logs_table extends Migration
{
    private $tableName = '{{%logs}}';

    public function safeUp()
    {
        $this->addColumn($this->tableName, 'order_number', $this->text());
    }

    public function safeDown()
    {
        $this->dropColumn($this->tableName, 'order_number');
    }
}
