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
            'activation_token'  => $this->string(255),
            'reset_token'       => $this->string(255),
            'account_status'    => $this->integer()->defaultValue(0),
            'otp_secret'        => $this->string(255),
            'otp_enabled'       => $this->integer()->defaultValue(0),
            'activation_token_expires_at' => $this->integer(),
            'reset_token_expires_at' => $this->integer(),
            'created_at'        => $this->integer(),
            'updated_at'        => $this->integer()
        ]);
        
        $this->createIndex('user__email_unique_index', 'user', ['username'], true);

        $this->createTable('user_token', [
            'id'            => $this->primaryKey(),
            'user_id'       => $this->integer(),
            'access_token'  => $this->string(64)->notNull(),
            'reset_token'   => $this->string(64)->notNull(),
            'ikm'           => $this->string(64)->notNull(),
            'expires_at'    => $this->integer()
        ]);

        $this->addForeignKey('user_token_fk', 'user_token', 'user_id', 'user', ['id'], 'CASCADE', 'CASCADE');
    }
    
    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('user_token');
        $this->dropTable('user');
    }
}