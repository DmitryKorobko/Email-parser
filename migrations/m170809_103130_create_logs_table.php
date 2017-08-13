<?php

use yii\db\Migration;

/**
 * Handles the creation of table `logs`.
 */
class m170809_103130_create_logs_table extends Migration
{
    private $tableName = '{{%logs}}';

    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->createTable($this->tableName, [
            'id'                        => $this->primaryKey(),
            'subject'                   => $this->text()->notNull(),
            'unique_message_identifier' => $this->string()->notNull(),
            'sender'                    => $this->string(255)->notNull(),
            'message_date'              => $this->integer()->notNull(),
            'complete'                  => $this->boolean()->notNull(),
            'order_id'                  => $this->integer(),
            'message_error'             => $this->text(),
            'href'                      => $this->text(),
            'created_at'                => $this->integer()->defaultValue(null),
            'updated_at'                => $this->integer()->defaultValue(null)

        ]);

        $this->createIndex('idx-message-unique_message_identifier',
            $this->tableName,
            'unique_message_identifier'
        );

        $this->createIndex('idx-message-message_date',
            $this->tableName,
            'message_date'
        );
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable($this->tableName);
    }
}