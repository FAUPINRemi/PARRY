<?php

namespace App\Service\AI;

class BudgetGuard
{
    private const MAX_MONTHLY_SPEND = 5.0; 
    private const SPEND_FILE = __DIR__ . '/../../../var/budget_spend.json';
    
    public function canMakeRequest(): bool
    {
        return $this->getCurrentSpend() < self::MAX_MONTHLY_SPEND;
    }
    
    public function trackRequest(float $estimatedCost): void
    {
        $currentSpend = $this->getCurrentSpend();
        $newSpend = $currentSpend + $estimatedCost;
        
        if ($newSpend >= self::MAX_MONTHLY_SPEND) {
            throw new \RuntimeException(
                "Budget mensuel atteint : {$newSpend}€ / " . self::MAX_MONTHLY_SPEND . "€"
            );
        }
        
        $this->saveSpend($newSpend);
    }
    
    public function getCurrentSpend(): float
    {
        if (!file_exists(self::SPEND_FILE)) {
            return 0.0;
        }
        
        $data = json_decode(file_get_contents(self::SPEND_FILE), true);
        
        if (!$data) {
            return 0.0;
        }
        
        // Réinitialiser si nouveau mois
        $currentMonth = date('Y-m');
        if (($data['month'] ?? '') !== $currentMonth) {
            $this->resetSpend();
            return 0.0;
        }
        
        return (float) ($data['spend'] ?? 0.0);
    }
    
    private function saveSpend(float $spend): void
    {
        $data = [
            'spend' => round($spend, 4),
            'month' => date('Y-m'),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        $dir = dirname(self::SPEND_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(self::SPEND_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function resetSpend(): void
    {
        $this->saveSpend(0.0);
    }
    
  
    public function getStats(): array
    {
        $currentSpend = $this->getCurrentSpend();
        $remaining = self::MAX_MONTHLY_SPEND - $currentSpend;
        $percentUsed = ($currentSpend / self::MAX_MONTHLY_SPEND) * 100;
        
        return [
            'current_spend' => round($currentSpend, 2),
            'max_budget' => self::MAX_MONTHLY_SPEND,
            'remaining' => round($remaining, 2),
            'percent_used' => round($percentUsed, 1),
            'month' => date('Y-m')
        ];
    }
}