<?php

use yii\db\Migration;

class m170815_182730_add_new_column extends Migration
{
    private $tableName = '{{%logs}}';

    public function safeUp()
    {
        $this->addColumn($this->tableName, 'html', $this->text());
    }

    public function safeDown()
    {
        $this->dropColumn($this->tableName, 'html');
    }
}

