<?php
require_once "BaseModel.php";
class TeamMember extends BaseModel {
    protected $table = "team_members";
    protected $fillable = [
        "team_id", "user_id", "role", "status", "created_at"
    ];
} 