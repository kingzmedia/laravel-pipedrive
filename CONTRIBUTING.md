# Contributing to Laravel Pipedrive

Thank you for considering contributing to Laravel Pipedrive! This document outlines the guidelines for contributing to this project.

## 🚀 **Getting Started**

### **Prerequisites**

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Composer
- A Pipedrive account for testing

### **Development Setup**

1. **Fork the repository**
   ```bash
   git clone https://github.com/your-username/laravel-pipedrive.git
   cd laravel-pipedrive
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up testing environment**
   ```bash
   cp .env.example .env.testing
   # Add your test Pipedrive credentials
   ```

4. **Run tests**
   ```bash
   composer test
   ```

## 📋 **How to Contribute**

### **Reporting Bugs**

1. **Check existing issues** first to avoid duplicates
2. **Use the bug report template** when creating new issues
3. **Include detailed information**:
   - Laravel version
   - PHP version
   - Package version
   - Steps to reproduce
   - Expected vs actual behavior
   - Error messages/logs

### **Suggesting Features**

1. **Check the roadmap** in `ROADMAP.md`
2. **Open a feature request** with:
   - Clear description of the feature
   - Use case and benefits
   - Proposed implementation (if any)

### **Submitting Pull Requests**

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Follow coding standards
   - Add tests for new functionality
   - Update documentation

3. **Test your changes**
   ```bash
   composer test
   composer test:coverage
   composer analyse
   ```

4. **Commit with clear messages**
   ```bash
   git commit -m "feat: add support for custom field validation"
   ```

5. **Push and create PR**
   ```bash
   git push origin feature/your-feature-name
   ```

## 🎯 **Development Guidelines**

### **Code Style**

- Follow **PSR-12** coding standards
- Use **Laravel conventions** for naming
- Write **clear, descriptive** variable and method names
- Add **PHPDoc comments** for all public methods

### **Testing**

- Write **unit tests** for all new functionality
- Maintain **minimum 80% code coverage**
- Use **Feature tests** for integration testing
- Mock external API calls in tests

### **Documentation**

- Update relevant **documentation files** in `docs/`
- Add **code examples** for new features
- Update **README.md** if needed
- Write documentation in **English only**

### **Commit Messages**

Use conventional commit format:
- `feat:` - New features
- `fix:` - Bug fixes
- `docs:` - Documentation changes
- `test:` - Test additions/changes
- `refactor:` - Code refactoring
- `style:` - Code style changes

## 🧪 **Testing**

### **Running Tests**

```bash
# Run all tests
composer test

# Run specific test suite
composer test -- --testsuite=Unit
composer test -- --testsuite=Feature

# Run with coverage
composer test:coverage

# Static analysis
composer analyse
```

### **Writing Tests**

```php
// Example unit test
class CustomFieldServiceTest extends TestCase
{
    public function test_can_sync_custom_fields()
    {
        // Arrange
        $service = app(PipedriveCustomFieldService::class);
        
        // Act
        $result = $service->syncCustomFields('deal');
        
        // Assert
        $this->assertTrue($result['success']);
    }
}
```

## 📚 **Documentation Standards**

### **File Structure**

```
docs/
├── authentication.md      # Auth setup
├── custom-fields.md      # Custom fields
├── database-structure.md # DB schema
├── entity-linking.md     # Model linking
├── events.md            # Event system
├── models-relationships.md # Models
├── performance.md       # Optimization
├── push-to-pipedrive.md # Sync to Pipedrive
├── relations-usage.md   # Using relations
├── synchronization.md   # Data sync
└── webhooks.md         # Webhook setup
```

### **Writing Style**

- Use **clear headings** with emojis
- Include **code examples** for all features
- Add **parameter descriptions** for methods
- Use **consistent formatting**

## 🔒 **Security**

- **Never commit** API keys or credentials
- **Validate all inputs** from external sources
- **Use Laravel's built-in** security features
- **Report security issues** privately to security@skeylup.com

## 📦 **Release Process**

1. **Update version** in `composer.json`
2. **Update CHANGELOG.md** with changes
3. **Tag the release**
   ```bash
   git tag -a v1.2.0 -m "Release v1.2.0"
   git push origin v1.2.0
   ```
4. **Create GitHub release** with changelog

## 🤝 **Code of Conduct**

- Be **respectful** and **inclusive**
- **Help others** learn and grow
- **Focus on constructive** feedback
- **Collaborate** effectively

## 📞 **Getting Help**

- **GitHub Issues** - For bugs and feature requests
- **GitHub Discussions** - For questions and ideas
- **Email** - kevin.eggermont@gmail.com for direct contact

## 🙏 **Recognition**

Contributors will be:
- **Listed** in the README credits
- **Mentioned** in release notes
- **Invited** to join the core team (for significant contributions)

Thank you for helping make Laravel Pipedrive better! 🚀
