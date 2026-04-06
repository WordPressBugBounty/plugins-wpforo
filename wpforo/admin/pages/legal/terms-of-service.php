<?php
/**
 * wpForo AI Features - Terms of Service
 *
 * This document governs the use of wpForo AI cloud services.
 * Last updated: March 2026
 *
 * @since 3.0.0
 */

if( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wpforo-ai-legal-document">
    <h1>wpForo AI Features - Terms of Service</h1>
    <p class="wpforo-ai-legal-updated"><strong>Last Updated:</strong> March 23, 2026</p>
    <p class="wpforo-ai-legal-updated"><strong>Effective Date:</strong> March 23, 2026</p>

    <div class="wpforo-ai-legal-toc">
        <h3>Table of Contents</h3>
        <ol>
            <li><a href="#acceptance">Acceptance of Terms</a></li>
            <li><a href="#description">Service Description</a></li>
            <li><a href="#eligibility">Eligibility and Registration</a></li>
            <li><a href="#data-local">Local Data Storage (WordPress)</a></li>
            <li><a href="#data-cloud">Cloud Data Processing and Storage</a></li>
            <li><a href="#credits">Credit System and Billing</a></li>
            <li><a href="#acceptable-use">Acceptable Use Policy</a></li>
            <li><a href="#intellectual-property">Intellectual Property Rights</a></li>
            <li><a href="#third-party">Third-Party Services</a></li>
            <li><a href="#disclaimers">Disclaimers and Limitations</a></li>
            <li><a href="#indemnification">Indemnification</a></li>
            <li><a href="#termination">Termination</a></li>
            <li><a href="#changes">Changes to Terms</a></li>
            <li><a href="#governing-law">Governing Law</a></li>
            <li><a href="#contact">Contact Information</a></li>
        </ol>
    </div>

    <hr>

    <h2 id="acceptance">1. Acceptance of Terms</h2>
    <p>wpForo AI Features are provided by gVectors AI. By clicking "I Agree" and connecting your WordPress website to wpForo AI Features ("Service"), you ("Customer", "you", or "your") agree to be bound by these Terms of Service ("Terms") and our Privacy Policy. These Terms constitute a legally binding agreement between you and gVectors Team ("Company", "we", "us", or "our").</p>
    <p><strong>IF YOU DO NOT AGREE TO THESE TERMS, DO NOT USE THE SERVICE.</strong></p>
    <p>You represent and warrant that you have the legal authority to bind the entity on whose behalf you are accepting these Terms. If you are accepting these Terms on behalf of an organization, you represent that you have the authority to do so.</p>

    <h2 id="description">2. Service Description</h2>
    <p>wpForo AI Features are a cloud-based AI-powered enhancement service for wpForo forum installations. The Service provides:</p>
    <ul>
        <li><strong>All AI features:</strong> AI-powered natural language search across your forum content</li>
        <li><strong>Content Indexing:</strong> Automated processing and vectorization of forum posts, topics, and replies</li>
        <li><strong>AI-Assisted Features:</strong> Topic suggestions, content analysis, and related content recommendations</li>
        <li><strong>Multi-Language Support:</strong> Search capabilities across 25+ languages</li>
    </ul>
    <p>The Service operates through a combination of local wpForo WordPress plugin functionality and cloud-based API services hosted on Amazon Web Services (AWS) processing by gVectors AI API.</p>

    <h2 id="eligibility">3. Eligibility and Registration</h2>
    <h3>3.1 Eligibility Requirements</h3>
    <p>To use the Service, you must:</p>
    <ul>
        <li>Be at least 18 years of age or the legal age of majority in your jurisdiction</li>
        <li>Have a valid WordPress installation with wpForo plugin version 3.0.0.0 or higher</li>
        <li>Have the legal right to operate your forum and process user-generated content</li>
        <li>Comply with all applicable laws and regulations in your jurisdiction</li>
    </ul>

    <h3>3.2 Account Registration</h3>
    <p>Upon registration, you will receive:</p>
    <ul>
        <li>A unique Tenant ID identifying your forum installation</li>
        <li>An API key for authenticating requests to our cloud services</li>
        <li>Access to a dedicated vector index for your forum content</li>
    </ul>
    <p>You are responsible for maintaining the confidentiality of your API key. You must immediately notify us of any unauthorized use of your credentials.</p>

    <h2 id="data-local">4. Local Data Storage (WordPress)</h2>
    <p><strong>This section describes data that remains on YOUR server and is NOT transmitted to our cloud services.</strong></p>

    <h3>4.1 Data Stored Locally</h3>
    <p>The following data is stored exclusively on your WordPress installation:</p>
    <ul>
        <li><strong>Plugin Settings:</strong> Configuration options, feature toggles, and display preferences</li>
        <li><strong>API Credentials:</strong> Your API key is stored encrypted in your WordPress database</li>
        <li><strong>Cache Data:</strong> Temporary search results and performance optimization data</li>
        <li><strong>User Preferences:</strong> Per-user settings and search history (if enabled)</li>
    </ul>

    <h3>4.2 Local Data Control</h3>
    <p>You maintain full control over locally stored data. This data:</p>
    <ul>
        <li>Never leaves your server unless explicitly transmitted via API calls</li>
        <li>Can be deleted at any time through WordPress admin settings</li>
        <li>Is subject to your own backup and security policies</li>
        <li>Is governed by your own privacy policy for your forum users</li>
    </ul>

    <h2 id="data-cloud">5. Cloud Data Processing and Storage</h2>
    <p><strong>IMPORTANT: This section describes data that IS transmitted to and processed by our cloud infrastructure.</strong></p>

    <h3>5.1 Data Transmitted to Cloud Services</h3>
    <p>When you use the Service, the following data is transmitted to our cloud infrastructure:</p>

    <h4>5.1.1 Forum Content Data</h4>
    <ul>
        <li>Forum topic titles and content</li>
        <li>Forum replies and comments</li>
        <li>Post metadata (timestamps, author IDs, forum categories)</li>
        <li>Topic status indicators (solved, closed, best answer)</li>
    </ul>

    <h4>5.1.2 Search Query Data</h4>
    <ul>
        <li>User search queries submitted through your forum</li>
        <li>Query metadata (timestamps, result counts)</li>
    </ul>

    <h4>5.1.3 Site Information</h4>
    <ul>
        <li>Your WordPress site URL</li>
        <li>Administrator email address</li>
        <li>WordPress and wpForo version numbers</li>
        <li>Forum structure (forum IDs, categories)</li>
    </ul>

    <h3>5.2 Cloud Data Processing</h3>
    <p>Your forum content is processed using the following technologies:</p>
    <ul>
        <li><strong>Amazon Bedrock:</strong> AWS managed AI/ML service for generating text embeddings</li>
        <li><strong>Amazon Nova Models:</strong> Foundation models for semantic understanding</li>
        <li><strong>Anthropic Claude:</strong> Advanced language models for AI-powered features</li>
        <li><strong>AWS S3 Vectors:</strong> Vector storage for semantic search capabilities</li>
    </ul>

    <h3>5.3 Cloud Data Storage</h3>
    <p>The following data is stored in our cloud infrastructure:</p>

    <h4>5.3.1 Vector Embeddings</h4>
    <ul>
        <li>Mathematical representations (vectors) of your forum content</li>
        <li>Stored in dedicated per-tenant vector indexes</li>
        <li>Retained until you delete content or disconnect the service</li>
    </ul>

    <h4>5.3.2 Metadata Records</h4>
    <ul>
        <li>Content synchronization state (hash values for change detection)</li>
        <li>Usage tracking records (API calls, credits consumed)</li>
        <li>Subscription and billing information</li>
    </ul>

    <h4>5.3.3 Operational Logs</h4>
    <ul>
        <li>API request logs (retained for 90 days)</li>
        <li>Error logs and debugging information</li>
        <li>Performance metrics</li>
    </ul>

    <h3>5.4 Data Security in Cloud</h3>
    <ul>
        <li><strong>Encryption in Transit:</strong> All data transmitted via HTTPS/TLS 1.2+</li>
        <li><strong>Encryption at Rest:</strong> AES-256 encryption for all stored data</li>
        <li><strong>Access Control:</strong> IAM-based least-privilege access policies</li>
        <li><strong>Tenant Isolation:</strong> Each customer has a dedicated, isolated vector index</li>
        <li><strong>Origin Validation:</strong> API requests validated against registered site URL</li>
    </ul>

    <h3>5.5 Data Retention and Deletion</h3>
    <ul>
        <li><strong>Active Subscription:</strong> Data retained indefinitely while subscription is active</li>
        <li><strong>After Disconnection:</strong> 30-day grace period, then permanent deletion</li>
        <li><strong>Operational Logs:</strong> Automatically deleted after 90 days</li>
        <li><strong>Immediate Deletion:</strong> Available upon request via "Clear All Data" function</li>
    </ul>

    <h2 id="credits">6. Credit System and Billing</h2>

    <h3>6.1 Credit-Based Usage</h3>
    <p>The Service operates on a credit-based system:</p>
    <ul>
        <li><strong>Indexing:</strong> 1 credit per topic indexed (includes all replies)</li>
        <li><strong>AI Features:</strong> Each AI action consumes credits based on the quality of AI model you select per feature: Fast: 1 credit/action, Balanced: 2 credits, Advanced: 3 credits, Premium: 4 credits.</li>
        <li><strong>Updates:</strong> Re-indexing unchanged content doesn't consume credits (deduplication benefit)</li>
        <li><strong>Smart Cache: gVectors AI includes a built-in smart cache system that significantly reduces credit usage. Repeated and similar requests are served from cache automatically — credits are only consumed for unique, first-time interactions. This means your credits go much further, and your community gets instant responses for previously answered questions without any additional cost.</strong>
    </ul>

    <h3>6.2 Credit Accumulation</h3>
    <ul>
        <li>Credits are <strong>interchangeable</strong> between features</li>
        <li>Credits <strong>accumulate</strong> up to your plan's maximum (one year of subscription plan credits)</li>
        <li>An <strong>active subscription</strong> is required to use credits</li>
    </ul>

    <h3>6.3 Subscription Plans</h3>
    <p>Available subscription tiers (subject to change):</p>
    <ul>
        <li><strong>Free Trial:</strong> 500 credits (30-day trial period)</li>
        <li><strong>Starter:</strong> 1,000 credits/month</li>
        <li><strong>Professional:</strong> 3,000 credits/month</li>
        <li><strong>Business:</strong> 9,000 credits/month</li>
        <li><strong>Enterprise:</strong> Custom credit allocation</li>
    </ul>

    <h3>6.4 Payment Terms</h3>
    <ul>
        <li>Payments processed through Freemius or Paddle payment platforms</li>
        <li>Subscription fees billed monthly or annually in advance</li>
        <li>Credits are non-refundable once consumed</li>
        <li>Unused credits remain available while subscription is active</li>
        <li>Upon subscription cancellation, remaining credits cannot be used until resubscription</li>
    </ul>

    <h3>6.5 Refund Policy</h3>
    <ul>
        <li>Refund requests considered within 14 days of initial purchase</li>
        <li style="color: #ff6e2d">Refunds not available for partially used credit allocations</li>
        <li style="color: #ff0000">Chargebacks may result in immediate account suspension</li>
    </ul>

    <h3>6.6 Credit Value and Service Discontinuation</h3>
    <p><strong>Important:</strong> Credits are a usage measurement unit for the Service and have the following limitations:</p>
    <ul>
        <li><strong>No Monetary Value:</strong> Credits have no cash value and cannot be exchanged, redeemed, or refunded for money under any circumstances</li>
        <li><strong>Non-Transferable:</strong> Credits cannot be transferred to other accounts or users</li>
        <li><strong>Service Discontinuation by Provider:</strong> If gVectors Team or gVectors AI (the AI service provider) discontinues the wpForo AI Features service for any reason—including but not limited to business decisions, financial constraints, technical limitations, third-party API changes, or regulatory requirements—all accumulated and unused credits will be immediately forfeited without compensation, refund, or any form of monetary reimbursement</li>
        <li><strong>No Guarantee of Perpetual Service:</strong> gVectors Team does not guarantee that the wpForo AI Features service will be available indefinitely. The service may be modified, suspended, or permanently discontinued at any time at the sole discretion of gVectors Team. By purchasing or using credits, you acknowledge and accept this risk</li>
        <li><strong>Advance Notice:</strong> In the event of planned service discontinuation by gVectors Team, we will endeavor to provide 30 days advance notice via email or plugin notification, but this notice period is not guaranteed in all circumstances (e.g., sudden technical failures, security incidents, or force majeure events)</li>
    </ul>
    <p style="color: #ff6e2d"><strong>By using the Service, you expressly acknowledge that credits are not a stored-value product, cryptocurrency, or any form of currency. You have no legal claim to monetary compensation for unused credits if the service is discontinued by gVectors Team.</strong></p>

    <h2 id="acceptable-use">7. Acceptable Use Policy</h2>

    <h3>7.1 Permitted Use</h3>
    <p>You may use the Service to:</p>
    <ul>
        <li>Index and search your own forum content</li>
        <li>Enhance your forum users' search experience</li>
        <li>Analyze and improve your forum content organization</li>
    </ul>

    <h3>7.2 Prohibited Use</h3>
    <p style="color: #ff0000">You may NOT use the Service to:</p>
    <ul>
        <li>Index content you do not have rights to process</li>
        <li>Store or transmit illegal, harmful, or infringing content</li>
        <li>Attempt to access other customers' data or systems</li>
        <li>Reverse engineer, decompile, or extract our algorithms or models</li>
        <li>Resell, sublicense, or redistribute the Service without authorization</li>
        <li>Use the Service to train competing AI models</li>
        <li>Circumvent usage limits, billing, or security measures</li>
        <li>Transmit malware, viruses, or malicious code</li>
        <li>Engage in automated scraping or abuse of API endpoints</li>
        <li>Process personal data in violation of GDPR, CCPA, or other privacy laws</li>
    </ul>

    <h3>7.3 Content Responsibility</h3>
    <p>You are solely responsible for:</p>
    <ul>
        <li>The legality of content indexed through the Service</li>
        <li>Obtaining necessary consents from your forum users</li>
        <li>Compliance with intellectual property rights</li>
        <li>Moderating and removing prohibited content from your forum</li>
    </ul>

    <h2 id="intellectual-property">8. Intellectual Property Rights</h2>

    <h3>8.1 Your Content</h3>
    <p>You retain all ownership rights to your forum content. By using the Service, you grant us a limited, non-exclusive license to:</p>
    <ul>
        <li>Process and index your content for providing the Service</li>
        <li>Create and store vector embeddings derived from your content</li>
        <li>Cache and optimize content delivery</li>
    </ul>
    <p>This license terminates when you disconnect from the Service and all data is deleted.</p>

    <h3>8.2 Our Technology</h3>
    <p>We retain all rights to:</p>
    <ul>
        <li>The wpForo AI plugin software and code</li>
        <li>Our cloud infrastructure and API systems</li>
        <li>Proprietary algorithms and processing methods</li>
        <li>Aggregated, anonymized usage statistics</li>
    </ul>

    <h3>8.3 Feedback</h3>
    <p>Any feedback, suggestions, or ideas you provide may be used by us without compensation or attribution.</p>

    <h2 id="third-party">9. Third-Party Services</h2>
    <p>The Service integrates with the following third-party providers:</p>

    <h3>9.1 Amazon Web Services (AWS)</h3>
    <ul>
        <li>Infrastructure hosting and data storage</li>
        <li>Amazon Bedrock for AI/ML processing</li>
        <li>Subject to <a href="https://aws.amazon.com/service-terms/" target="_blank" rel="noopener">AWS Service Terms</a></li>
    </ul>

    <h3>9.2 AI Model Providers</h3>
    <ul>
        <li><strong>Amazon Nova:</strong> Foundation models via AWS Bedrock</li>
        <li><strong>Anthropic Claude:</strong> Language models via AWS Bedrock</li>
        <li>Content processed temporarily; not stored by model providers</li>
    </ul>

    <h3>9.3 Freemius</h3>
    <ul>
        <li>Payment processing and subscription management</li>
        <li>Subject to <a href="https://freemius.com/terms/" target="_blank" rel="noopener">Freemius Terms of Service</a></li>
    </ul>

    <h3>9.4 Paddle</h3>
    <ul>
        <li>Payment processing and subscription management (alternative payment provider)</li>
        <li>Subject to <a href="https://www.paddle.com/legal/terms" target="_blank" rel="noopener">Paddle Terms of Service</a></li>
        <li>Paddle acts as Merchant of Record for transactions processed through their platform</li>
    </ul>

    <h2 id="disclaimers">10. Disclaimers and Limitations</h2>

    <h3>10.1 Service Provided "AS IS"</h3>
    <p>THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO:</p>
    <ul>
        <li>MERCHANTABILITY</li>
        <li>FITNESS FOR A PARTICULAR PURPOSE</li>
        <li>NON-INFRINGEMENT</li>
        <li>ACCURACY OR RELIABILITY OF SEARCH RESULTS</li>
        <li>UNINTERRUPTED OR ERROR-FREE OPERATION</li>
    </ul>

    <h3>10.2 AI Limitations</h3>
    <p>You acknowledge that:</p>
    <ul>
        <li>AI-powered search uses approximate matching and may not return all relevant results</li>
        <li>Semantic understanding has inherent limitations and may misinterpret content</li>
        <li>AI features are provided for assistance only and should not be solely relied upon for critical decisions</li>
        <li>Model outputs may occasionally contain errors or inaccuracies</li>
    </ul>

    <h3>10.3 Limitation of Liability</h3>
    <p>TO THE MAXIMUM EXTENT PERMITTED BY LAW:</p>
    <ul>
        <li>Our total liability shall not exceed the amount you paid for the Service in the 12 months preceding the claim</li>
        <li>We are not liable for indirect, incidental, special, consequential, or punitive damages</li>
        <li>We are not liable for lost profits, data loss, or business interruption</li>
        <li>We are not liable for any third-party claims against you</li>
    </ul>

    <h3>10.4 Service Availability</h3>
    <p>We do not guarantee:</p>
    <ul>
        <li>100% uptime or availability</li>
        <li>Specific response times or latency</li>
        <li>Preservation of your data in case of catastrophic failure</li>
    </ul>
    <p>We recommend maintaining your own backups of critical forum content.</p>

    <h2 id="indemnification">11. Indemnification</h2>
    <p>You agree to indemnify, defend, and hold harmless gVectors Team and its officers, directors, employees, and agents from any claims, damages, losses, or expenses (including reasonable attorney fees) arising from:</p>
    <ul>
        <li>Your use of the Service</li>
        <li>Your forum content or user-generated content</li>
        <li>Your violation of these Terms</li>
        <li>Your violation of any third-party rights</li>
        <li>Any claims by your forum users related to the Service</li>
    </ul>

    <h2 id="termination">12. Termination</h2>

    <h3>12.1 Termination by You</h3>
    <p>You may terminate your use of the Service at any time by:</p>
    <ul>
        <li>Disconnecting via the wpForo AI settings page</li>
        <li>Canceling your subscription through Freemius or Paddle</li>
    </ul>

    <h3>12.2 Termination by Us</h3>
    <p>We may suspend or terminate your access immediately if:</p>
    <ul>
        <li>You violate these Terms or our Acceptable Use Policy</li>
        <li>Your payment method fails and is not updated</li>
        <li>We receive a valid legal request requiring termination</li>
        <li>We discontinue the Service (with 30 days notice when possible)</li>
    </ul>

    <h3>12.3 Effect of Termination</h3>
    <ul>
        <li>Your access to cloud services will be revoked</li>
        <li>You have 30 days to export any data you need</li>
        <li>After 30 days, all cloud-stored data will be permanently deleted</li>
        <li>Local plugin data remains on your server for you to manage</li>
        <li>Unused credits are forfeited upon termination</li>
    </ul>

    <h2 id="changes">13. Changes to Terms</h2>
    <p>We may modify these Terms at any time. When we make changes:</p>
    <ul>
        <li>We will update the "Last Updated" date at the top of this document</li>
        <li>For material changes, we will notify you via email or plugin notification</li>
        <li>Continued use of the Service after changes constitutes acceptance</li>
        <li>If you disagree with changes, you must stop using the Service</li>
    </ul>

    <h2 id="governing-law">14. Governing Law and Disputes</h2>

    <h3>14.1 Governing Law</h3>
    <p>These Terms are governed by the laws of the State of Delaware, United States, without regard to conflict of law principles.</p>

    <h3>14.2 Dispute Resolution</h3>
    <p>Any disputes arising from these Terms or the Service shall be resolved through:</p>
    <ol>
        <li><strong>Informal Resolution:</strong> Contact us first to attempt resolution</li>
        <li><strong>Mediation:</strong> If informal resolution fails, non-binding mediation</li>
        <li><strong>Arbitration:</strong> Binding arbitration under AAA Commercial Arbitration Rules</li>
    </ol>
    <p>You waive any right to participate in class action lawsuits or class-wide arbitration.</p>

    <h3>14.3 Venue</h3>
    <p>Any legal proceedings shall be conducted in the state or federal courts located in Delaware, USA.</p>

    <h2 id="contact">15. Contact Information</h2>
    <p>For questions about these Terms or the Service:</p>
    <ul>
        <li><strong>Support Portal:</strong> <a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank" rel="noopener">Open Support Ticket</a></li>
        <li><strong>Website:</strong> <a href="https://wpforo.com" target="_blank" rel="noopener">wpforo.com</a></li>
        <li><strong>Company:</strong> gVectors Team</li>
        <li><strong>Service:</strong> <a href="https://v3.wpforo.com/gvectors-ai/" target="_blank">gVectors AI</a></li>
    </ul>

    <hr>

    <p class="wpforo-ai-legal-footer">
        <em>By using wpForo AI Features, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.</em>
    </p>
</div>
