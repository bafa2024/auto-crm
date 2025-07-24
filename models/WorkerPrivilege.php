<?php
require_once "BaseModel.php";
class WorkerPrivilege extends BaseModel {
    protected $table = "worker_privileges";
    protected $fillable = [
        "team_id", "user_id", "privilege", "allowed", "created_at"
    ];
} 