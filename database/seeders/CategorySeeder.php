<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get brands by slug
        $mejba = Brand::where('slug', 'mejba')->first();
        $ramlit = Brand::where('slug', 'ramlit')->first();
        $colorpark = Brand::where('slug', 'colorpark')->first();
        $xcybersecurity = Brand::where('slug', 'xcybersecurity')->first();

        // Categories for Mejba Personal Portfolio
        if ($mejba) {
            Category::create([
                'brand_id' => $mejba->id,
                'name' => 'Web Development',
                'slug' => 'web-development',
                'keywords' => [
                    'Laravel', 'PHP', 'JavaScript', 'React', 'Vue.js', 'Node.js',
                    'API development', 'RESTful', 'GraphQL', 'TypeScript',
                    'Frontend', 'Backend', 'Full-stack', 'Web applications',
                    'Progressive Web Apps', 'PWA', 'SPA', 'Single Page Application',
                ],
                'trend_sources' => ['dev.to', 'hashnode', 'medium', 'reddit/r/webdev'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $mejba->id,
                'name' => 'Design',
                'slug' => 'design',
                'keywords' => [
                    'UI/UX', 'User Interface', 'User Experience', 'Design Systems',
                    'Tailwind CSS', 'CSS', 'Responsive Design', 'Mobile-first',
                    'Accessibility', 'WCAG', 'Design patterns', 'Wireframing',
                    'Prototyping', 'Figma', 'Adobe XD', 'Sketch',
                ],
                'trend_sources' => ['dribbble', 'behance', 'awwwards'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $mejba->id,
                'name' => 'Technology',
                'slug' => 'technology',
                'keywords' => [
                    'DevOps', 'CI/CD', 'Docker', 'Kubernetes', 'Cloud computing',
                    'AWS', 'Azure', 'Google Cloud', 'Serverless', 'Microservices',
                    'Git', 'Version control', 'Testing', 'TDD', 'BDD',
                    'Performance optimization', 'Security', 'Best practices',
                ],
                'trend_sources' => ['hackernews', 'tech crunch', 'the verge'],
                'active' => true,
            ]);
        }

        // Categories for Ramlit Limited
        if ($ramlit) {
            Category::create([
                'brand_id' => $ramlit->id,
                'name' => 'Business',
                'slug' => 'business',
                'keywords' => [
                    'Digital transformation', 'Business strategy', 'Enterprise solutions',
                    'ROI', 'Cost optimization', 'Efficiency', 'Productivity',
                    'Business intelligence', 'Analytics', 'Data-driven decisions',
                    'Automation', 'Process improvement', 'Scalability',
                ],
                'trend_sources' => ['harvard business review', 'forbes', 'mckinsey'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $ramlit->id,
                'name' => 'Software Development',
                'slug' => 'software-development',
                'keywords' => [
                    'Enterprise software', 'Custom development', 'Software architecture',
                    'Agile', 'Scrum', 'Project management', 'SDLC',
                    'Code quality', 'Technical debt', 'Refactoring',
                    'Integration', 'API', 'Middleware', 'Legacy modernization',
                ],
                'trend_sources' => ['infoq', 'dzone', 'software engineering daily'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $ramlit->id,
                'name' => 'Cloud Services',
                'slug' => 'cloud-services',
                'keywords' => [
                    'Cloud migration', 'Hybrid cloud', 'Multi-cloud', 'Cloud native',
                    'Infrastructure as code', 'IaC', 'Terraform', 'CloudFormation',
                    'Cloud security', 'Compliance', 'Disaster recovery', 'Backup',
                    'Cost management', 'Resource optimization', 'Monitoring',
                ],
                'trend_sources' => ['aws blog', 'azure blog', 'google cloud blog'],
                'active' => true,
            ]);
        }

        // Categories for ColorPark Creative Agency
        if ($colorpark) {
            Category::create([
                'brand_id' => $colorpark->id,
                'name' => 'Design',
                'slug' => 'design',
                'keywords' => [
                    'Graphic design', 'Visual design', 'Typography', 'Color theory',
                    'Logo design', 'Brand identity', 'Illustration', 'Motion graphics',
                    'UI design', 'UX design', 'Design thinking', 'Creative direction',
                    '3D design', 'Animation', 'Video editing', 'Digital art',
                ],
                'trend_sources' => ['behance', 'dribbble', 'awwwards', 'designboom'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $colorpark->id,
                'name' => 'Branding',
                'slug' => 'branding',
                'keywords' => [
                    'Brand strategy', 'Brand positioning', 'Brand identity',
                    'Visual identity', 'Brand guidelines', 'Style guide',
                    'Rebranding', 'Brand refresh', 'Brand storytelling',
                    'Brand voice', 'Brand messaging', 'Brand experience',
                ],
                'trend_sources' => ['brandnew', 'logo design love', 'branding magazine'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $colorpark->id,
                'name' => 'Creative',
                'slug' => 'creative',
                'keywords' => [
                    'Creative strategy', 'Concept development', 'Ideation',
                    'Art direction', 'Creative campaigns', 'Storytelling',
                    'Content creation', 'Photography', 'Videography',
                    'Social media content', 'Visual content', 'Creative trends',
                ],
                'trend_sources' => ['creative bloq', 'the drum', 'adweek'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $colorpark->id,
                'name' => 'Marketing',
                'slug' => 'marketing',
                'keywords' => [
                    'Digital marketing', 'Content marketing', 'Social media marketing',
                    'SEO', 'SEM', 'Email marketing', 'Marketing automation',
                    'Influencer marketing', 'Performance marketing', 'Growth hacking',
                    'Customer engagement', 'Marketing analytics', 'Campaign management',
                ],
                'trend_sources' => ['marketing week', 'adage', 'social media examiner'],
                'active' => true,
            ]);
        }

        // Categories for xCyberSecurity Global Services
        if ($xcybersecurity) {
            Category::create([
                'brand_id' => $xcybersecurity->id,
                'name' => 'Cybersecurity',
                'slug' => 'cybersecurity',
                'keywords' => [
                    'Information security', 'Cyber defense', 'Security operations',
                    'Incident response', 'Security monitoring', 'SIEM',
                    'Penetration testing', 'Vulnerability assessment', 'Security audit',
                    'Encryption', 'Authentication', 'Authorization', 'Zero trust',
                ],
                'trend_sources' => ['krebs on security', 'dark reading', 'threatpost'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $xcybersecurity->id,
                'name' => 'Threat Intelligence',
                'slug' => 'threat-intelligence',
                'keywords' => [
                    'Threat hunting', 'Malware analysis', 'APT', 'Advanced persistent threat',
                    'Indicators of compromise', 'IOC', 'Threat actors', 'Attack vectors',
                    'Cyber threats', 'Ransomware', 'Phishing', 'Social engineering',
                    'Zero-day', 'Exploits', 'Threat landscape', 'Security intelligence',
                ],
                'trend_sources' => ['recorded future', 'threat intelligence platform', 'mitre att&ck'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $xcybersecurity->id,
                'name' => 'Compliance',
                'slug' => 'compliance',
                'keywords' => [
                    'GDPR', 'HIPAA', 'PCI DSS', 'SOC 2', 'ISO 27001',
                    'Compliance frameworks', 'Regulatory compliance', 'Data protection',
                    'Privacy', 'Audit', 'Risk assessment', 'Risk management',
                    'Governance', 'Security policies', 'Compliance reporting',
                ],
                'trend_sources' => ['compliance week', 'nist', 'cis controls'],
                'active' => true,
            ]);

            Category::create([
                'brand_id' => $xcybersecurity->id,
                'name' => 'Network Security',
                'slug' => 'network-security',
                'keywords' => [
                    'Firewall', 'IDS', 'IPS', 'Intrusion detection', 'Intrusion prevention',
                    'Network monitoring', 'DDoS protection', 'VPN', 'Network segmentation',
                    'Access control', 'Network architecture', 'Secure networking',
                    'WAF', 'Web application firewall', 'Network security architecture',
                ],
                'trend_sources' => ['network world', 'cisco blog', 'palo alto networks blog'],
                'active' => true,
            ]);
        }
    }
}
