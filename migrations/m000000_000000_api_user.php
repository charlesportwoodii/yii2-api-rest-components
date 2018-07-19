<?php

use yii\db\Migration;

class m000000_000000_api_user extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('user', [
            'id'                => $this->primaryKey(),
            'email'             => $this->string(255)->notNull(),
            'username'          => $this->string(255)->notNull(),
            'password'          => $this->string(255)->notNull(),
            'verified'          => $this->integer()->defaultValue(0),
            'otp_secret'        => $this->string(600),
            'otp_enabled'       => $this->integer()->defaultValue(0),
            'created_at'        => $this->integer(),
            'updated_at'        => $this->integer()
        ]);
        
        $this->createIndex('user__email_unique_index', 'user', ['email'], true);
        $this->createIndex('user__username_unique_index', 'user', ['username'], true);
    }
    
    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('user');
    }
}
