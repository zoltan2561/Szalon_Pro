{{ (int) $amount === 0 ? 'Ingyenes' : number_format($amount, 0, ',', ' ').' Ft'.(($kind ?? 'fixed') === 'from' ? '-tól' : '') }}
