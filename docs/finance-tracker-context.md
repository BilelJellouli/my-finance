# Finance Tracker — Project Context

## What this is

A private, single-user app to give me a bird's-eye view of my financial life across my personal finances and the LLCs I own.

It is a **planning and visibility tool**, not accounting software. I am not trying to replace a bookkeeper, generate invoices, or do my taxes. I want to open it, see where I stand, and make decisions about what to pay, when, and from which pocket.

---

## Who uses it

Just me. Single user. No teams, no sharing, no client portal.

---

## The problem I'm solving

I have money and obligations spread across several entities:

- My personal finances
- Multiple LLCs (two now, more in the future)

Each entity has its own bank accounts, and some accounts are in different currencies. Money moves between entities (I get salary or dividends from my LLCs, I sometimes borrow from one of my LLCs, etc.) and between accounts of the same entity (I move euros into my main TND account).

Right now I have no single place to answer questions like:

- How much money do I actually have, across everything?
- How much do I owe, and to whom?
- How much do people (or my own LLCs) owe me?
- What payments are coming up in the next few weeks?
- If I pull a chunk of dividends out of one LLC next month, what does my situation look like?

---

## What I want to see

When I open the app, the main view should answer four things at a glance:

1. **Where I stand right now** — total cash, total debt, net position
2. **What's coming up** — scheduled payments and transfers in the near future
3. **Per-entity breakdown** — for each LLC and my personal finances, what's the position
4. **What if** — quick projections when I'm planning moves

---

## Core concepts in plain language

### Entities
The "buckets" I'm tracking. Right now: Personal, LLC 1, LLC 2. More LLCs later.

### Accounts
Each entity has one or more accounts. Some are real bank accounts (possibly in different currencies). Some are "virtual" — for example, money my LLC technically owes me as dividends or salary that I haven't actually pulled out yet. The virtual balance is money I can draw on, even though it's not sitting in a personal bank account.

For each entity I designate one **main account** — the one I think of as the entity's primary pocket. Other accounts (like a euro account on the side) feed into it when I transfer money.

### Debts
Money owed in one direction or the other. A debt can be:

- Between me and an outside party (a person, a bank)
- Between me and one of my LLCs
- Between two of my LLCs

Important: when the debt is between two of my own entities, it's the **same debt** seen from two sides. It should never be entered twice, and it shouldn't make my consolidated total look bigger than it really is.

### Payments
Movements of money between entities, or between an entity and someone external. Examples: a salary paid from LLC 1 to me, a dividend payout, a repayment on a loan, an expense paid to a vendor. Payments can be scheduled in the future or recorded after the fact.

### Transfers
Movements of money between two accounts of the **same entity** — typically across currencies. Example: moving 500 EUR from my euro account into my main TND account. Each transfer records the exchange rate I actually got at the time, so I have a history of how rates have moved, but I'm not trying to compute fancy FX gains.

### Recurring payments
Things that happen on a schedule — monthly salary, quarterly repayments, etc. I want flexibility: simple intervals, specific days of the month, or fully custom schedules.

### Currencies and exchange rates
Several currencies are in play (TND, EUR, USD, maybe more). I'll enter exchange rates manually as I learn them — no automatic syncing. When I look at totals, I want to choose what currency the totals are shown in.

---

## How I want to use it

### Daily / weekly
Open the dashboard. See where I stand. Mark off payments that actually went through. Reconcile balances when I check my bank.

### When something changes
Add a new debt when I borrow or lend. Add a new recurring payment when I set one up. Update a balance when I move money.

### When I'm planning
This is the part most personal-finance apps don't do well. I want two ways to plan:

- **Quick scratchpad** — a calculator-like area where I throw in amounts in different currencies, see the total converted, no need to save anything. Useful when I'm on the phone or in a meeting.

- **Saved scenarios** — named "what-ifs" I can save and revisit. "What if I pull 10k EUR from LLC 1 next month and repay 5k to LLC 2?" I want to see the projected dashboard side-by-side with the real one, so I can compare before I commit.

---

## What this is *not*

To keep the scope honest:

- Not an invoicing tool
- Not a bookkeeping or accounting system
- Not a tax calculator
- Not a bank-syncing or Open Banking app — all balances are entered manually
- Not double-entry accounting
- Not a budgeting / envelope tool
- Not a multi-user / multi-tenant app
- Not a mobile app
- No email reminders (at least not in the first version)

If a feature starts to feel like accounting software or like something a CPA would build, it's out of scope.

---

## Priorities, in order

1. I can set up my entities, accounts, and current balances
2. I can record debts (including between my own entities, without double-counting)
3. I can schedule and record payments and transfers
4. I can see a clear bird's-eye dashboard
5. I can run quick what-if calculations and save scenarios

Everything else is nice-to-have.

---

## Tone / feel

Calm, clear, dense with information but not cluttered. The kind of dashboard I'd actually want to open on a Sunday morning to plan the week. Numbers should be large and readable. Important warnings (missing exchange rate, overdue payment) should be obvious but not noisy.

---

## What to build next

Start small. One entity, one account, one payment, a minimal dashboard. Get that working end-to-end before adding the rest. We'll figure out the data model and technical choices step by step as we go — don't try to design the whole thing up front.
