export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <img
            {...props}
            src="/cb-logo.jpg"
            alt="Chat Bridge"
            className={`rounded-full object-cover ${className}`.trim()}
        />
    );
}
