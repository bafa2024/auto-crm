<?php
require_once "BaseModel.php";

class EmailTemplate extends BaseModel {
    protected $table = "email_templates";
    protected $fillable = [
        "name", "subject", "content", "template_type", "is_active", "created_by"
    ];
}