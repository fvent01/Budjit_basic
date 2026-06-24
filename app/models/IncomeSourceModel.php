<?php
// app/models/IncomeSourceModel.php

class IncomeSourceModel extends Model
{
    protected string $table = 'income_sources';

    public function getActiveForUser(int $userId): array
    {
        return $this->query(
            "SELECT * FROM income_sources
             WHERE user_id = ? AND is_active = 1
             ORDER BY name",
            [$userId]
        )->fetchAll();
    }
}
