<?php

class PersonaEmailAliasDA extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "id", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "user_id", [
                "isForeignKey" => true,
                "referencesTable" => "persons",
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "email", [
                "isRequired" => true,
                "isUnique" => true,
                "columnType" => "TEXT",
                "processOnAll" => function ($rawEmail) { return strtolower($rawEmail); }
            ]),
            new GTKColumnMapping($this, "is_primary", [
                "valueWhenNewForUser" => false,
                "columnType" => "BOOLEAN"
            ]),
            new GTKColumnMapping($this, "created_at", [
                "valueWhenNewForUser" => function($user, $item) {
                    return date("Y-m-d H:i:s");
                },
                "columnType" => "DATETIME"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }
    
    /**
     * Add an email alias for a user
     * 
     * @param int $personId The ID of the person
     * @param string $email The email address to add
     * @param bool $isPrimary Whether this is the primary email
     * @return array The created email alias record
     */
    public function addEmailForPerson($personId, $email, $isPrimary = false)
    {
        // Check if email already exists
        $existingEmail = $this->getOne("email", $email);
        if ($existingEmail) {
            return $existingEmail;
        }
        
        // If this is set as primary, unset any existing primary emails
        if ($isPrimary) {
            $this->unsetPrimaryEmailsForPerson($personId);
        }
        
        $emailAlias = [
            "user_id" => $personId,
            "email" => $email,
            "is_primary" => $isPrimary ? 1 : 0,
            "created_at" => date("Y-m-d H:i:s")
        ];
        
        return $this->insert($emailAlias);
    }
    
    /**
     * Unset all primary emails for a person
     * 
     * @param int $personId The ID of the person
     */
    public function unsetPrimaryEmailsForPerson($personId)
    {
        $query = new SelectQuery($this);
        $query->where("user_id", "=", $personId);
        $query->where("is_primary", "=", 1);
        $primaryEmails = $query->executeAndReturnAll();
        
        foreach ($primaryEmails as $email) {
            $email["is_primary"] = 0;
            $this->update($email);
        }
    }
    
    /**
     * Get all email aliases for a person
     * 
     * @param int $personId The ID of the person
     * @return array List of email aliases
     */
    public function getEmailsForPerson($personId)
    {
        $query = new SelectQuery($this);
        $query->where("user_id", "=", $personId);
        return $query->executeAndReturnAll();
    }
    
    /**
     * Get the primary email for a person
     * 
     * @param int $personId The ID of the person
     * @return string|null The primary email or null if not found
     */
    public function getPrimaryEmailForPerson($personId)
    {
        $query = new SelectQuery($this);
        $query->where("user_id", "=", $personId);
        $query->where("is_primary", "=", 1);
        $primaryEmail = $query->executeAndReturnOne();
        
        if ($primaryEmail) {
            return $primaryEmail["email"];
        }
        
        return null;
    }
    
    /**
     * Find a person by email alias
     * 
     * @param string $email The email to search for
     * @return int|null The person ID or null if not found
     */
    public function findPersonByEmail($email)
    {
        $email = strtolower($email);
        $emailAlias = $this->getOne("email", $email);
        
        if ($emailAlias) {
            return $emailAlias["user_id"];
        }
        
        return null;
    }
} 