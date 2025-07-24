<?php
require_once "BaseModel.php";
class Team extends BaseModel {
    protected $table = "teams";
    protected $fillable = [
        "name", "description", "created_by", "created_at", "updated_at"
    ];
} 