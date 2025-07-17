<?php
require_once "BaseModel.php";

class Contact extends BaseModel {
    protected $table = "contacts";
    protected $fillable = [
        "first_name", "last_name", "email", "phone", "company", "job_title",
        "lead_source", "interest_level", "status", "notes", "assigned_agent_id", "created_by"
    ];
}