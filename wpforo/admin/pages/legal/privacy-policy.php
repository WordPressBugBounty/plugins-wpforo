<?php
/**
 * wpForo AI Features - Privacy Policy
 *
 * This document describes how we collect, use, and protect your data.
 * Last updated: March 2026
 *
 * @since 3.0.0
 */

if( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wpforo-ai-legal-document">
    <h1>wpForo AI Features - Privacy Policy</h1>
    <p class="wpforo-ai-legal-updated"><strong>Last Updated:</strong> March 23, 2026</p>
    <p class="wpforo-ai-legal-updated"><strong>Effective Date:</strong> March 23, 2026</p>

    <div class="wpforo-ai-legal-toc">
        <h3>Table of Contents</h3>
        <ol>
            <li><a href="#introduction">Introduction</a></li>
            <li><a href="#definitions">Definitions</a></li>
            <li><a href="#data-controller">Data Controller and Processor Roles</a></li>
            <li><a href="#local-data">Data Stored Locally (Your Server)</a></li>
            <li><a href="#cloud-data">Data Processed in the Cloud</a></li>
            <li><a href="#how-we-use">How We Use Your Data</a></li>
            <li><a href="#ai-processing">AI and Machine Learning Processing</a></li>
            <li><a href="#data-sharing">Data Sharing and Third Parties</a></li>
            <li><a href="#data-retention">Data Retention</a></li>
            <li><a href="#data-security">Data Security</a></li>
            <li><a href="#your-rights">Your Rights</a></li>
            <li><a href="#international">International Data Transfers</a></li>
            <li><a href="#children">Children's Privacy</a></li>
            <li><a href="#changes">Changes to This Policy</a></li>
            <li><a href="#contact">Contact Us</a></li>
        </ol>
    </div>

    <hr>

    <h2 id="introduction">1. Introduction</h2>
    <p>wpForo AI features are provided by <a href="https://v3.wpforo.com/gvectors-ai/" target="_blank">gVectors AI</a>. This Privacy Policy explains how gVectors Team ("we", "us", "our") collects, uses, stores, and protects information when you use the wpForo AI Features service ("Service").</p>
    <p>By using the Service, you agree to the collection and use of information as described in this Privacy Policy. This policy should be read in conjunction with our Terms of Service.</p>
    <p><strong>Important:</strong> This Privacy Policy covers data processed by our Service. As a forum operator, you are responsible for your own privacy policy that governs how you collect and process your forum users' data.</p>

    <h2 id="definitions">2. Definitions</h2>
    <ul>
        <li><strong>"Personal Data"</strong> means any information relating to an identified or identifiable natural person.</li>
        <li><strong>"Forum Content"</strong> means topics, posts, replies, and other content created by users on your forum.</li>
        <li><strong>"Vector Embeddings"</strong> means mathematical representations of text content used for semantic search.</li>
        <li><strong>"Customer"</strong> means the forum administrator who registers for and uses the Service.</li>
        <li><strong>"End Users"</strong> means visitors and registered users of your forum.</li>
        <li><strong>"Processing"</strong> means any operation performed on data, including collection, storage, and deletion.</li>
    </ul>

    <h2 id="data-controller">3. Data Controller and Processor Roles</h2>

    <h3>3.1 Your Role as Data Controller</h3>
    <p>As the forum operator, <strong>you are the Data Controller</strong> for:</p>
    <ul>
        <li>Your forum users' personal data</li>
        <li>Forum content created by your users</li>
        <li>Decisions about what data to index and process</li>
    </ul>
    <p>You are responsible for:</p>
    <ul>
        <li>Obtaining necessary consents from your forum users</li>
        <li>Updating your own privacy policy to disclose use of AI services</li>
        <li>Responding to your users' data subject requests</li>
        <li>Ensuring lawful basis for processing forum content</li>
    </ul>

    <h3>3.2 Our Role as Data Processor</h3>
    <p>gVectors Team (through <a href="https://v3.wpforo.com/gvectors-ai/" target="_blank">gVectors AI</a>) acts as a <strong>Data Processor</strong> for forum content you submit to the Service. We:</p>
    <ul>
        <li>Process data only according to your instructions (indexing requests)</li>
        <li>Implement appropriate security measures</li>
        <li>Assist you in responding to data subject requests</li>
        <li>Delete data upon your request or service termination</li>
    </ul>

    <h3>3.3 Our Role as Data Controller</h3>
    <p>We are the <strong>Data Controller</strong> for:</p>
    <ul>
        <li>Your account information (email, site URL)</li>
        <li>Billing and subscription data</li>
        <li>Service usage analytics</li>
        <li>Technical logs and operational data</li>
    </ul>

    <h2 id="local-data">4. Data Stored Locally (Your Server)</h2>
    <p><strong>The following data is stored only on your WordPress server and is NOT transmitted to our cloud services:</strong></p>

    <h3>4.1 Plugin Configuration</h3>
    <ul>
        <li>Feature enable/disable settings</li>
        <li>Display preferences and customizations</li>
        <li>Search result formatting options</li>
    </ul>

    <h3>4.2 Credentials (Encrypted)</h3>
    <ul>
        <li>API key (stored encrypted in WordPress database)</li>
        <li>Tenant ID</li>
        <li>Connection status</li>
    </ul>

    <h3>4.3 Cache Data</h3>
    <ul>
        <li>Temporary search results cache</li>
        <li>Performance optimization data</li>
    </ul>

    <h3>4.4 Your Control Over Local Data</h3>
    <p>You have complete control over locally stored data:</p>
    <ul>
        <li>Delete via plugin settings at any time</li>
        <li>Included in standard WordPress backup procedures</li>
        <li>Subject to your server's security measures</li>
        <li>Never shared with us without explicit API calls</li>
    </ul>

    <h2 id="cloud-data">5. Data Processed in the Cloud</h2>
    <p><strong>IMPORTANT: The following data IS transmitted to and processed by our cloud infrastructure when you use the Service.</strong></p>

    <h3>5.1 Forum Content Data</h3>
    <table class="wpforo-ai-legal-table">
        <thead>
            <tr>
                <th>Data Type</th>
                <th>Examples</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Topic Content</td>
                <td>Titles, descriptions, body text</td>
                <td>Indexing for semantic search</td>
            </tr>
            <tr>
                <td>Replies</td>
                <td>User responses, comments</td>
                <td>Indexing for semantic search</td>
            </tr>
            <tr>
                <td>Metadata</td>
                <td>Post IDs, timestamps, forum categories</td>
                <td>Result organization and filtering</td>
            </tr>
            <tr>
                <td>Status Flags</td>
                <td>Solved, best answer, closed</td>
                <td>Search result ranking</td>
            </tr>
        </tbody>
    </table>

    <h3>5.2 Account Information</h3>
    <table class="wpforo-ai-legal-table">
        <thead>
            <tr>
                <th>Data Type</th>
                <th>Examples</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Administrator Email</td>
                <td>admin@yourforum.com</td>
                <td>Account identification, notifications</td>
            </tr>
            <tr>
                <td>Site URL</td>
                <td>https://yourforum.com</td>
                <td>Origin validation, tenant identification</td>
            </tr>
            <tr>
                <td>Software Versions</td>
                <td>WordPress 6+, wpForo 3+</td>
                <td>Compatibility and support</td>
            </tr>
        </tbody>
    </table>

    <h3>5.3 Search Query Data</h3>
    <table class="wpforo-ai-legal-table">
        <thead>
            <tr>
                <th>Data Type</th>
                <th>Examples</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Search Queries</td>
                <td>"how to configure email notifications"</td>
                <td>Processing search requests</td>
            </tr>
            <tr>
                <td>Query Metadata</td>
                <td>Timestamp, results count</td>
                <td>Analytics and billing</td>
            </tr>
        </tbody>
    </table>

    <h3>5.4 Usage and Billing Data</h3>
    <table class="wpforo-ai-legal-table">
        <thead>
            <tr>
                <th>Data Type</th>
                <th>Examples</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Credit Usage</td>
                <td>Credits consumed per operation</td>
                <td>Billing and limits enforcement</td>
            </tr>
            <tr>
                <td>API Calls</td>
                <td>Request counts, endpoints accessed</td>
                <td>Usage analytics, troubleshooting</td>
            </tr>
            <tr>
                <td>Subscription Status</td>
                <td>Plan type, expiration date</td>
                <td>Service access control</td>
            </tr>
        </tbody>
    </table>

    <h2 id="how-we-use">6. How We Use Your Data</h2>

    <h3>6.1 Primary Purposes</h3>
    <ul>
        <li><strong>Providing the Service:</strong> Processing forum content to enable semantic search</li>
        <li><strong>AI Features:</strong> Powering topic suggestions, related content, and search</li>
        <li><strong>Account Management:</strong> Managing your subscription and authentication</li>
        <li><strong>Billing:</strong> Tracking usage and processing payments</li>
    </ul>

    <h3>6.2 Operational Purposes</h3>
    <ul>
        <li><strong>Service Improvement:</strong> Analyzing aggregated usage patterns</li>
        <li><strong>Technical Support:</strong> Diagnosing issues when you contact support</li>
        <li><strong>Security:</strong> Detecting and preventing abuse or unauthorized access</li>
        <li><strong>Legal Compliance:</strong> Meeting legal obligations</li>
    </ul>

    <h3>6.3 What We Do NOT Do</h3>
    <ul>
        <li style="color: #1e7e34">Sell your data to third parties</li>
        <li style="color: #1e7e34">Use your forum content for advertising</li>
        <li style="color: #1e7e34">Share your data with other customers</li>
        <li style="color: #1e7e34">Train our own AI models on your specific content (without explicit consent)</li>
        <li style="color: #1e7e34">Access your content except as needed to provide the Service</li>
    </ul>

    <h2 id="ai-processing">7. AI and Machine Learning Processing</h2>

    <h3>7.1 AI Services Used</h3>
    <p>Your forum content is processed using the following AI technologies:</p>

    <h4>Amazon Bedrock</h4>
    <ul>
        <li><strong>Purpose:</strong> Managed AI/ML service for text embeddings</li>
        <li><strong>Data Handling:</strong> Content processed in real-time; not stored by Amazon Bedrock</li>
        <li><strong>Privacy:</strong> Subject to <a href="https://aws.amazon.com/bedrock/faqs/" target="_blank" rel="noopener">AWS Bedrock privacy practices</a></li>
    </ul>

    <h4>Amazon Nova Models</h4>
    <ul>
        <li><strong>Purpose:</strong> Foundation models for semantic understanding</li>
        <li><strong>Data Handling:</strong> Content processed temporarily for embedding generation</li>
        <li><strong>Training:</strong> Your content is NOT used to train Nova models</li>
    </ul>

    <h4>Anthropic Claude (via Bedrock)</h4>
    <ul>
        <li><strong>Purpose:</strong> Advanced language understanding for AI features</li>
        <li><strong>Data Handling:</strong> Content processed temporarily; not stored by Anthropic</li>
        <li><strong>Training:</strong> Your content is NOT used to train Claude models (API usage)</li>
    </ul>

    <h3>7.2 Vector Embeddings</h3>
    <p>When we process your forum content:</p>
    <ol>
        <li>Text is converted into numerical vectors (by Amazon embedding models)</li>
        <li>Original text is NOT stored in the vector database</li>
        <li>Vectors capture semantic meaning but cannot be reversed to original text</li>
        <li>Metadata (IDs, timestamps) is stored alongside vectors for result retrieval</li>
    </ol>

    <h3>7.3 AI Processing Limitations</h3>
    <p>We commit to:</p>
    <ul>
        <li style="color: #1e7e34">Using AI only for providing Service features</li>
        <li style="color: #1e7e34">Not using your content to train proprietary models</li>
        <li style="color: #1e7e34">Not sharing your content with AI providers beyond processing needs</li>
        <li style="color: #1e7e34">Providing transparency about AI processing methods</li>
    </ul>

    <h2 id="data-sharing">8. Data Sharing and Third Parties</h2>

    <h3>8.1 Service Providers (Sub-processors)</h3>
    <p>We share data with the following service providers who process data on our behalf:</p>

    <table class="wpforo-ai-legal-table">
        <thead>
            <tr>
                <th>Provider</th>
                <th>Purpose</th>
                <th>Data Shared</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Amazon Web Services (AWS)</td>
                <td>Cloud infrastructure, AI processing</td>
                <td>All cloud-processed data</td>
                <td>United States (us-east-1) / Can be changed for customers with Enterprise Subscription Plan</td>
            </tr>
            <tr>
                <td>Amazon Bedrock</td>
                <td>AI embedding generation</td>
                <td>Forum content (temporary)</td>
                <td>United States</td>
            </tr>
            <tr>
                <td>Freemius</td>
                <td>Payment processing</td>
                <td>Email, site URL, billing info</td>
                <td>Israel/United States</td>
            </tr>
            <tr>
                <td>Paddle</td>
                <td>Payment processing (Merchant of Record)</td>
                <td>Email, site URL, billing info</td>
                <td>United Kingdom/United States</td>
            </tr>
        </tbody>
    </table>

    <h3>8.2 Legal Requirements</h3>
    <p>We may disclose data when required by:</p>
    <ul>
        <li>Valid legal process (court order, subpoena)</li>
        <li>Law enforcement requests with proper authority</li>
        <li>Protection of our rights, property, or safety</li>
        <li>Emergency situations involving potential harm</li>
    </ul>

    <h3>8.3 Business Transfers</h3>
    <p>In case of merger, acquisition, or sale of assets:</p>
    <ul>
        <li>Your data may be transferred to the acquiring entity</li>
        <li>You will be notified of any such transfer</li>
        <li>This Privacy Policy will continue to apply until you are notified otherwise</li>
    </ul>

    <h3>8.4 No Sale of Personal Data</h3>
    <p style="color: #1e7e34">We do NOT sell, rent, or trade your personal data or forum content to any third parties for their marketing purposes.</p>

    <h2 id="data-retention">9. Data Retention</h2>

    <h3>9.1 Retention Periods</h3>
    <table class="wpforo-ai-legal-table">
        <thead>
            <tr>
                <th>Data Type</th>
                <th>Retention Period</th>
                <th>Deletion Method</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Vector Embeddings</td>
                <td>Until deletion request or account termination</td>
                <td>Automatic on disconnect + 30 days</td>
            </tr>
            <tr>
                <td>Account Information</td>
                <td>Duration of subscription + 30 days</td>
                <td>Automatic after grace period</td>
            </tr>
            <tr>
                <td>API Logs</td>
                <td>90 days</td>
                <td>Automatic TTL deletion</td>
            </tr>
            <tr>
                <td>Usage Analytics</td>
                <td>90 days</td>
                <td>Automatic TTL deletion</td>
            </tr>
            <tr>
                <td>Billing Records</td>
                <td>7 years (legal requirement)</td>
                <td>Manual after legal period</td>
            </tr>
            <tr>
                <td>Support Tickets</td>
                <td>2 years after resolution</td>
                <td>Manual review and deletion</td>
            </tr>
        </tbody>
    </table>

    <h3>9.2 Deletion Upon Request</h3>
    <p>You can request immediate deletion of your data:</p>
    <ul>
        <li><strong>Forum Content:</strong> Use "Clear All Indexed Data" in plugin settings</li>
        <li><strong>Account Data:</strong> Disconnect and request full deletion via support</li>
        <li><strong>All Data:</strong> Contact support for complete data erasure</li>
    </ul>

    <h3>9.3 Post-Termination</h3>
    <ul>
        <li>30-day grace period for reactivation</li>
        <li>After 30 days: Permanent deletion of all cloud data</li>
        <li>Billing records retained as legally required</li>
        <li>Aggregated, anonymized statistics may be retained</li>
    </ul>

    <h2 id="data-security">10. Data Security</h2>

    <h3>10.1 Technical Measures</h3>
    <ul>
        <li><strong>Encryption in Transit:</strong> TLS 1.2+ for all communications</li>
        <li><strong>Encryption at Rest:</strong> AES-256 encryption for all stored data</li>
        <li><strong>Access Control:</strong> IAM-based least-privilege access</li>
        <li><strong>Network Security:</strong> AWS VPC isolation, security groups</li>
        <li><strong>API Security:</strong> Origin validation, rate limiting, API key hashing</li>
    </ul>

    <h3>10.2 Organizational Measures</h3>
    <ul>
        <li>Access limited to authorized personnel only</li>
        <li>Regular security reviews and updates</li>
        <li>Incident response procedures in place</li>
        <li>Vendor security assessments</li>
    </ul>

    <h3>10.3 Tenant Isolation</h3>
    <ul>
        <li>Each customer has a dedicated, isolated vector index</li>
        <li>No customer can access another customer's data</li>
        <li>API keys validated against registered domain</li>
    </ul>

    <h3>10.4 Breach Notification</h3>
    <p>In the event of a data breach affecting your data:</p>
    <ul>
        <li>We will notify you within 72 hours of discovery</li>
        <li>Notification will include nature of breach and affected data</li>
        <li>We will provide guidance on protective measures</li>
        <li>We will cooperate with any required regulatory notifications</li>
    </ul>

    <h2 id="your-rights">11. Your Rights</h2>

    <h3>11.1 GDPR Rights (EEA Residents)</h3>
    <p>If you are in the European Economic Area, you have the right to:</p>
    <ul>
        <li><strong>Access:</strong> Request a copy of your personal data</li>
        <li><strong>Rectification:</strong> Request correction of inaccurate data</li>
        <li><strong>Erasure:</strong> Request deletion of your data ("right to be forgotten")</li>
        <li><strong>Restriction:</strong> Request limited processing of your data</li>
        <li><strong>Portability:</strong> Receive your data in a machine-readable format</li>
        <li><strong>Objection:</strong> Object to processing based on legitimate interests</li>
        <li><strong>Withdraw Consent:</strong> Withdraw consent at any time</li>
    </ul>

    <h3>11.2 CCPA Rights (California Residents)</h3>
    <p>If you are a California resident, you have the right to:</p>
    <ul>
        <li><strong>Know:</strong> What personal information we collect and how it's used</li>
        <li><strong>Delete:</strong> Request deletion of your personal information</li>
        <li><strong>Opt-Out:</strong> Opt-out of sale of personal information (we don't sell data)</li>
        <li><strong>Non-Discrimination:</strong> Not be discriminated against for exercising rights</li>
    </ul>

    <h3>11.3 Exercising Your Rights</h3>
    <p>To exercise any of these rights:</p>
    <ol>
        <li>Use in-plugin controls for data deletion</li>
        <li>Open a support ticket at <a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank" rel="noopener">our support portal</a></li>
        <li>We will respond within 30 days (or sooner if required by law)</li>
        <li>We may need to verify your identity before processing requests</li>
    </ol>

    <h3>11.4 Your Forum Users' Rights</h3>
    <p>As the Data Controller for your forum users:</p>
    <ul>
        <li>You are responsible for handling their data requests</li>
        <li>We will assist you in fulfilling requests related to indexed content</li>
        <li>Contact us if you need to delete specific user content from our indexes</li>
    </ul>

    <h2 id="international">12. International Data Transfers</h2>

    <h3>12.1 Data Location</h3>
    <p>Our primary data processing occurs in:</p>
    <ul>
        <li><strong>United States:</strong> AWS us-east-1 region (Northern Virginia)</li>
    </ul>

    <h3>12.2 Transfer Mechanisms</h3>
    <p>For data transferred from the EEA/UK to the US, we rely on:</p>
    <ul>
        <li>AWS's Standard Contractual Clauses (SCCs)</li>
        <li>Data Processing Agreements with sub-processors</li>
        <li>Appropriate supplementary measures as required</li>
    </ul>

    <h3>12.3 Data Processing Agreement</h3>
    <p>Enterprise customers may request a formal Data Processing Agreement (DPA) that includes:</p>
    <ul>
        <li>Standard Contractual Clauses</li>
        <li>Technical and organizational measures</li>
        <li>Sub-processor list and notification procedures</li>
    </ul>

    <h2 id="children">13. Children's Privacy</h2>
    <p>The Service is not directed at children under 18 years of age. We do not knowingly collect personal data from children.</p>
    <p>If you become aware that a child has provided personal data through your forum that has been indexed by our Service, please contact us immediately, and we will delete such data.</p>

    <h2 id="changes">14. Changes to This Policy</h2>
    <p>We may update this Privacy Policy periodically. When we make changes:</p>
    <ul>
        <li>We will update the "Last Updated" date</li>
        <li>For material changes, we will notify you via email or plugin notification</li>
        <li>Continued use after changes constitutes acceptance</li>
        <li>Previous versions will be archived and available upon request</li>
    </ul>

    <h2 id="contact">15. Contact Us</h2>
    <p>For questions, concerns, or requests regarding this Privacy Policy:</p>
    <ul>
        <li><strong>Support Portal:</strong> <a href="https://v3.wpforo.com/login-register/?tab=login" target="_blank" rel="noopener">Open Support Ticket</a></li>
        <li><strong>wpForo Website:</strong> <a href="https://wpforo.com" target="_blank" rel="noopener">wpforo.com</a></li>
        <li><strong>wpForo Community:</strong> <a href="https://wpforo.com/community/" target="_blank" rel="noopener">wpforo.com</a></li>
        <li><strong>Company:</strong> gVectors Team</li>
        <li><strong>Service:</strong> <a href="https://v3.wpforo.com/gvectors-ai/" target="_blank">gVectors AI</a></li>
        <li><strong>gVectors Website:</strong> <a href="https://gvectors.com" target="_blank">gvectors.com</a></li>
    </ul>

    <h3>Data Protection Inquiries</h3>
    <p>For data protection specific inquiries or to exercise your rights, please open a support ticket with the subject line "Data Protection Request".</p>

    <hr>

    <p class="wpforo-ai-legal-footer">
        <em>By using wpForo AI Features, you acknowledge that you have read and understood this Privacy Policy.</em>
    </p>
</div>
