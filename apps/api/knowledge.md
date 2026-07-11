knowledge_base:
  name: Support Ticket Classification
  version: 2.0

system_instruction: |
  You are an intelligent ticket classification engine.
  Your responsibility is to determine the PRIMARY reason why the customer created the ticket.

  Rules:
    - Read the complete ticket including subject and description.
    - Determine the customer's primary intent.
    - Return EXACTLY one category.
    - Do not invent categories.
    - Ignore greetings and signatures.
    - If multiple categories match, use the priority rules.
    - If confidence is below 70%, classify as "General Inquiry".
    - Return a confidence score between 0 and 100.
    - Return a short explanation.

priority_order:
  - Security
  - Refund
  - Billing & Payment
  - Technical Issue
  - Course Related
  - Subscription
  - Account & Login
  - Registration
  - Order Management
  - Feature Request
  - Complaint
  - Feedback
  - General Inquiry
  - Spam

categories:

  - id: CAT001
    name: Account & Login
    intent: Customer cannot access their account.

    keywords:
      - login
      - sign in
      - signin
      - password
      - forgot password
      - reset password
      - otp
      - authentication
      - account locked

    positive_examples:
      - I cannot login.
      - Forgot my password.
      - OTP is not arriving.
      - Authentication failed.

    negative_examples:
      - I want to change my email.
      - Please refund my payment.

    route_to: Authentication Team

  - id: CAT002
    name: Registration

    intent: Customer cannot create a new account.

    keywords:
      - signup
      - sign up
      - register
      - registration
      - create account
      - verification email

    positive_examples:
      - Unable to register.
      - Email verification failed.

    route_to: Authentication Team

  - id: CAT003
    name: Billing & Payment

    intent: Payment or invoice related issue.

    keywords:
      - payment
      - invoice
      - billing
      - transaction
      - charged
      - card
      - debit
      - credit

    positive_examples:
      - Payment failed.
      - Invoice missing.
      - Card declined.

    exclusions:
      - refund request

    route_to: Finance

  - id: CAT004
    name: Refund

    intent: Customer wants money back.

    keywords:
      - refund
      - money back
      - reimbursement
      - duplicate payment
      - cancel payment

    positive_examples:
      - Please refund my payment.
      - I paid twice.
      - I need my money back.

    overrides:
      - Billing & Payment

    route_to: Finance

  - id: CAT005
    name: Technical Issue

    intent: Product is malfunctioning.

    keywords:
      - bug
      - error
      - crash
      - exception
      - loading
      - slow
      - timeout
      - not working
      - blank screen
      - internal server error

    positive_examples:
      - Website crashed.
      - Application is throwing an error.
      - Page keeps loading.

    route_to: Engineering

  - id: CAT006
    name: Feature Request

    intent: Customer requests a new capability.

    keywords:
      - feature
      - enhancement
      - improvement
      - request
      - add option
      - support

    positive_examples:
      - Please add dark mode.
      - Add export to Excel.

    exclusions:
      - existing functionality not working

    route_to: Product Team

  - id: CAT007
    name: Course Related

    intent: Learning platform issue.

    keywords:
      - course
      - lesson
      - lecture
      - quiz
      - assignment
      - certificate
      - instructor
      - video

    positive_examples:
      - Unable to access my course.
      - Certificate is missing.

    route_to: Education Support

  - id: CAT008
    name: Subscription

    intent: Subscription or plan management.

    keywords:
      - subscription
      - plan
      - renewal
      - upgrade
      - downgrade
      - cancel subscription

    route_to: Billing Team

  - id: CAT009
    name: Security

    intent: Security or unauthorized access.

    keywords:
      - hacked
      - breach
      - unauthorized
      - suspicious
      - malware
      - phishing

    positive_examples:
      - My account was hacked.
      - Someone logged into my account.

    highest_priority: true

    route_to: Security Team

  - id: CAT010
    name: Complaint

    intent: Customer expresses dissatisfaction.

    keywords:
      - complaint
      - poor service
      - unhappy
      - disappointed
      - frustrated

    route_to: Customer Success

  - id: CAT011
    name: Feedback

    intent: General feedback without requesting support.

    keywords:
      - feedback
      - suggestion
      - review
      - appreciate

    route_to: Product Team

  - id: CAT012
    name: General Inquiry

    intent: General questions that do not match other categories.

    keywords: []

    default_category: true

    route_to: Customer Support

  - id: CAT013
    name: Spam

    intent: Irrelevant or promotional content.

    keywords:
      - casino
      - crypto
      - loan
      - marketing
      - promotion

    auto_close: true

output_format:

  category:
  confidence:
  route_to:
  matched_keywords:
  explanation:

example:

  input:
    subject: Charged Twice
    description: My card was charged twice and I need my money back.

  output:
    category: Refund
    confidence: 99
    route_to: Finance
    matched_keywords:
      - charged twice
      - money back
    explanation: Customer is explicitly requesting a refund after a duplicate payment.

classification_logic:

  1. Read subject.
  2. Read description.
  3. Identify primary customer intent.
  4. Match categories using intent first.
  5. Validate with keywords.
  6. Apply exclusion rules.
  7. Apply priority rules.
  8. Calculate confidence.
  9. Return one category only.

confidence_guidelines:

  95-100:
    Strong intent match with multiple keywords.

  85-94:
    Clear intent with one strong keyword.

  70-84:
    Partial keyword match but clear context.

  below_70:
    Default to General Inquiry.
