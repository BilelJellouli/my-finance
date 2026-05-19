export type TransactionKind = 'cash' | 'bank_transfer' | 'card';

export type TransactionAccountRef = {
    id: number;
    name: string;
    currency: string;
    entity: { id: number; name: string; color: string } | null;
};

export type TransactionCounterpartyRef = {
    id: number;
    name: string;
    kind: 'internal' | 'external';
};

export type TransactionPlannedRef = {
    id: number;
    purpose: string | null;
    due_date: string | null;
    amount: string;
    counterparty: TransactionCounterpartyRef | null;
};

export type TransactionListItem = {
    id: number;
    amount: string;
    currency: string;
    kind: TransactionKind;
    occurred_on: string;
    note: string | null;
    from_account: TransactionAccountRef | null;
    to_account: TransactionAccountRef | null;
    counterparty: TransactionCounterpartyRef | null;
    planned_transaction: TransactionPlannedRef | null;
};

export type EntityWithAccounts = {
    id: number;
    name: string;
    type: 'personal' | 'llc';
    color: string;
    accounts: { id: number; name: string; currency: string; current_balance: string }[];
};

export type OpenPlannedRef = {
    id: number;
    direction: 'incoming' | 'outgoing';
    amount: string;
    currency: string;
    due_date: string | null;
    purpose: string | null;
    owner_entity: { id: number; name: string; color: string };
    counterparty: TransactionCounterpartyRef;
};
